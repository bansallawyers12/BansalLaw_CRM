<?php

namespace App\Services\CrmMcp;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\Staff;
use App\Services\ClientAccountTabService;
use App\Services\ClientNotesListService;
use App\Support\ActivityFeedQuery;
use App\Support\GlobalSearchPhoneMatcher;
use App\Support\NoteDescriptionHtml;
use App\Support\StaffClientVisibility;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Read-only CRM data for the remote MCP server (Grok Bot / Cursor).
 * Always enforces StaffClientVisibility for the authenticated staff token.
 */
class CrmMcpReadService
{
    public function __construct(
        private readonly ClientNotesListService $notesListService,
        private readonly ClientAccountTabService $accountTabService,
        private readonly CrmMcpDocumentDownloadService $documentDownloadService,
    ) {}

    public function resolveStaff(?Authenticatable $user = null): Staff
    {
        $user = $user ?? Auth::guard('admin')->user() ?? Auth::user();

        if (! $user instanceof Staff) {
            throw new CrmMcpAccessException('Staff authentication required.', 401);
        }

        return $user;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, ?string $type, Staff $staff): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new CrmMcpAccessException('Search query is required.', 422);
        }

        $limit = max(1, min(50, (int) config('crm_mcp.search_limit', 20)));
        $types = $this->normalizeRecordTypes($type);

        $builder = Admin::query()
            ->with(['company:id,admin_id,company_name'])
            ->whereIn('type', $types)
            ->whereNull('is_deleted')
            ->where(function ($q) {
                $q->where('is_archived', 0)->orWhereNull('is_archived');
            });

        StaffClientVisibility::excludeSuperAdminOnlyLockedClientsFromAdminQuery($builder, $staff);

        $like = '%'.addcslashes(mb_strtolower($query, 'UTF-8'), '%_\\').'%';
        $builder->where(function ($q) use ($like, $query) {
            $q->whereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(TRIM(COALESCE(first_name, \'\') || \' \' || COALESCE(last_name, \'\'))) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(client_id, \'\')) LIKE ?', [$like])
                ->orWhereRaw('CAST(id AS TEXT) = ?', [(string) (int) $query]);

            if (ctype_digit($query)) {
                $q->orWhere('id', (int) $query);
            }

            $phoneVariants = GlobalSearchPhoneMatcher::searchDigitVariants($query);
            if ($phoneVariants !== []) {
                $q->orWhere(function ($phoneQ) use ($phoneVariants) {
                    $phoneSql = GlobalSearchPhoneMatcher::digitsSql('phone');
                    $combinedSql = GlobalSearchPhoneMatcher::digitsSql(
                        "CONCAT(COALESCE(country_code, ''), COALESCE(phone, ''))"
                    );
                    foreach ($phoneVariants as $i => $variant) {
                        $likePhone = '%'.$variant.'%';
                        if ($i === 0) {
                            $phoneQ->whereRaw("{$phoneSql} LIKE ?", [$likePhone])
                                ->orWhereRaw("{$combinedSql} LIKE ?", [$likePhone]);
                        } else {
                            $phoneQ->orWhereRaw("{$phoneSql} LIKE ?", [$likePhone])
                                ->orWhereRaw("{$combinedSql} LIKE ?", [$likePhone]);
                        }
                    }
                });
            }
        });

        $candidates = $builder
            ->orderByDesc('updated_at')
            ->limit(max($limit * 5, $limit))
            ->get([
                'id', 'type', 'client_id', 'first_name', 'last_name', 'email', 'phone',
                'country_code', 'lead_status', 'status', 'is_company', 'is_archived', 'user_id',
            ]);

        $rows = $candidates
            ->filter(fn (Admin $row) => StaffClientVisibility::canAccessClientOrLead((int) $row->id, $staff))
            ->take($limit)
            ->values();

        $this->audit($staff, 'search', ['q' => $query, 'type' => $type, 'count' => $rows->count()]);

        return $rows->map(fn (Admin $row) => $this->serializeRecordSummary($row, $staff))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecord(int $adminId, Staff $staff): array
    {
        $record = $this->findAccessibleRecord($adminId, $staff);
        $this->audit($staff, 'get_record', ['admin_id' => $adminId, 'type' => $record->type]);

        return $this->serializeRecordDetail($record);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMatters(int $adminId, Staff $staff): array
    {
        $this->findAccessibleRecord($adminId, $staff);

        $matters = ClientMatter::query()
            ->with([
                'matter:id,title,nick_name',
                'legalPractitioner:id,first_name,last_name',
                'personResponsible:id,first_name,last_name',
                'personAssisting:id,first_name,last_name',
                'workflowStage:id,name',
            ])
            ->where('client_id', $adminId)
            ->orderByDesc('id')
            ->get();

        $this->audit($staff, 'list_matters', ['admin_id' => $adminId, 'count' => $matters->count()]);

        return $matters->map(function (ClientMatter $m) {
            return [
                'id' => (int) $m->id,
                'client_unique_matter_no' => $m->client_unique_matter_no,
                'matter_status' => $m->matter_status,
                'matter_title' => $m->matter?->title,
                'matter_nick_name' => $m->matter?->nick_name,
                'deadline' => optional($m->deadline)?->toDateString(),
                'workflow_stage' => $m->workflowStage?->name,
                'legal_practitioner' => $this->staffName($m->legalPractitioner),
                'person_responsible' => $this->staffName($m->personResponsible),
                'person_assisting' => $this->staffName($m->personAssisting),
                'case_detail' => $m->case_detail,
                'updated_at' => optional($m->updated_at)?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * @return array{notes: list<array<string, mixed>>, total: int, has_more: bool, next_offset: int}
     */
    public function listNotes(int $adminId, Staff $staff, int $offset = 0, ?int $limit = null): array
    {
        $record = $this->findAccessibleRecord($adminId, $staff);
        $noteType = $record->type === 'lead' ? 'lead' : 'client';
        $limit = $limit ?? (int) config('crm_mcp.notes_limit', 30);

        $fetched = $this->notesListService->fetchNotes($adminId, $noteType, $offset, $limit);

        $this->audit($staff, 'list_notes', [
            'admin_id' => $adminId,
            'offset' => $offset,
            'count' => $fetched['notes']->count(),
        ]);

        $notes = $fetched['notes']->map(function ($note) {
            $html = NoteDescriptionHtml::forDisplay($note->description ?? '');
            $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return [
                'id' => (int) $note->id,
                'title' => $note->title,
                'description_text' => Str::limit($plain, 4000, '…'),
                'type' => $note->type,
                'pin' => (int) ($note->pin ?? 0),
                'matter_id' => $note->matter_id,
                'author' => $this->staffName($note->user),
                'created_at' => optional($note->created_at)?->toIso8601String(),
                'updated_at' => optional($note->updated_at)?->toIso8601String(),
            ];
        })->all();

        return [
            'notes' => $notes,
            'total' => $fetched['total'],
            'has_more' => $fetched['has_more'],
            'next_offset' => $fetched['next_offset'],
        ];
    }

    /**
     * @return array{activities: list<array<string, mixed>>, has_more: bool, page: int, per_page: int}
     */
    public function listActivities(int $adminId, Staff $staff, int $page = 1, ?int $perPage = null, ?string $keyword = null): array
    {
        $this->findAccessibleRecord($adminId, $staff);

        $perPage = $perPage ?? (int) config('crm_mcp.activities_limit', 40);
        $perPage = max(1, min(ActivityFeedQuery::PER_PAGE_MAX, $perPage));
        $page = max(1, $page);

        $request = Request::create('/', 'GET', array_filter([
            'id' => $adminId,
            'page' => $page,
            'per_page' => $perPage,
            'keyword' => $keyword,
        ], fn ($v) => $v !== null && $v !== ''));

        $query = ActivitiesLog::query()
            ->where('activities_logs.client_id', $adminId)
            ->with('creator:id,first_name,last_name');
        ActivityFeedQuery::apply($query, $request);
        $query->orderByDesc('activities_logs.created_at')->orderByDesc('activities_logs.id');

        $rows = $query->skip(($page - 1) * $perPage)->take($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $activities = $rows->take($perPage);

        $this->audit($staff, 'list_activities', [
            'admin_id' => $adminId,
            'page' => $page,
            'count' => $activities->count(),
        ]);

        $payload = $activities->map(function (ActivitiesLog $row) {
            $html = NoteDescriptionHtml::forDisplay($row->description ?? '');
            $plain = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return [
                'id' => (int) $row->id,
                'activity_type' => $row->activity_type,
                'subject' => ActivitiesLog::displaySubjectWithoutStaffPrefix(
                    $row->activity_type ?? null,
                    $row->subject ?? null
                ),
                'description_text' => Str::limit($plain, 2000, '…'),
                'pin' => (int) ($row->pin ?? 0),
                'task_group' => $row->task_group,
                'followup_date' => $row->followup_date,
                'created_by' => $this->staffName($row->creator),
                'created_at' => optional($row->created_at)?->toIso8601String(),
            ];
        })->all();

        return [
            'activities' => $payload,
            'has_more' => $hasMore,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBilling(int $adminId, Staff $staff, ?int $clientMatterId = null): array
    {
        $this->findAccessibleRecord($adminId, $staff);

        $data = $this->accountTabService->build($adminId, $clientMatterId);
        $invoiceLimit = max(1, min(100, (int) config('crm_mcp.invoice_rows_limit', 25)));

        $this->audit($staff, 'get_billing', [
            'admin_id' => $adminId,
            'client_matter_id' => $data['clientMatterId'],
        ]);

        return [
            'client_matter_id' => $data['clientMatterId'],
            'trust_balance' => $data['trustBalance'],
            'outstanding_balance' => $data['outstandingBalance'],
            'invoiced_total' => $data['invoicedTotal'],
            'costs_disclosure' => $data['costsDisclosure'],
            'exceeds_disclosure' => $data['exceedsDisclosure'],
            'invoices' => $data['invoiceRows']->take($invoiceLimit)->map(fn ($row) => [
                'id' => (int) ($row->id ?? 0),
                'receipt_id' => $row->receipt_id ?? null,
                'trans_no' => $row->trans_no ?? null,
                'trans_date' => $row->trans_date ?? null,
                'balance_amount' => isset($row->balance_amount) ? (float) $row->balance_amount : null,
                'partial_paid_amount' => isset($row->partial_paid_amount) ? (float) $row->partial_paid_amount : null,
                'client_matter_id' => $row->client_matter_id ?? null,
                'status' => $row->status ?? null,
            ])->values()->all(),
            'invoice_has_more' => $data['invoiceHasMore'] || $data['invoiceRows']->count() > $invoiceLimit,
            'trust_has_more' => $data['trustHasMore'],
            'office_has_more' => $data['officeHasMore'],
        ];
    }

    /**
     * @return array{documents: list<array<string, mixed>>, total: int, has_more: bool}
     */
    public function listDocuments(
        int $adminId,
        Staff $staff,
        ?string $folderName = null,
        ?string $docType = null,
        ?int $clientMatterId = null,
        ?int $limit = null,
    ): array {
        $record = $this->findAccessibleRecord($adminId, $staff);
        $limit = $limit ?? (int) config('crm_mcp.documents_limit', 50);
        $limit = max(1, min(150, $limit));

        $query = Document::query()
            ->select([
                'id', 'client_id', 'lead_id', 'client_matter_id', 'file_name', 'filetype',
                'folder_name', 'doc_type', 'type', 'checklist', 'status', 'file_size',
                'created_at', 'updated_at',
            ])
            ->whereNull('not_used_doc')
            ->where(function ($q) use ($adminId) {
                $q->where('client_id', $adminId)->orWhere('lead_id', $adminId);
            });

        StaffClientVisibility::restrictDocumentEloquentQuery($query, $staff);

        if ($folderName !== null && $folderName !== '') {
            $query->where('folder_name', $folderName);
        }
        if ($docType !== null && $docType !== '') {
            if (in_array($docType, ['matter', 'visa'], true)) {
                $query->whereIn('doc_type', ['matter', 'visa']);
            } else {
                $query->where('doc_type', $docType);
            }
        }
        if ($clientMatterId !== null && $clientMatterId > 0) {
            $query->where('client_matter_id', $clientMatterId);
        }

        // Prefer the record type when set on documents.
        $query->where(function ($q) use ($record) {
            $q->where('type', $record->type)->orWhereNull('type');
        });

        $total = (clone $query)->count();
        $docs = $query->orderByDesc('updated_at')->limit($limit + 1)->get();
        $hasMore = $docs->count() > $limit;
        $docs = $docs->take($limit);

        $this->audit($staff, 'list_documents', [
            'admin_id' => $adminId,
            'count' => $docs->count(),
            'folder' => $folderName,
        ]);

        return [
            'documents' => $docs->map(fn (Document $d) => [
                'id' => (int) $d->id,
                'file_name' => $d->file_name,
                'filetype' => $d->filetype,
                'folder_name' => $d->folder_name,
                'doc_type' => $d->doc_type,
                'type' => $d->type,
                'checklist' => $d->checklist,
                'status' => $d->status,
                'client_matter_id' => $d->client_matter_id,
                'file_size' => $d->file_size,
                'created_at' => optional($d->created_at)?->toIso8601String(),
                'updated_at' => optional($d->updated_at)?->toIso8601String(),
            ])->all(),
            'total' => $total,
            'has_more' => $hasMore || $total > $limit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function downloadDocument(int $documentId, Staff $staff): array
    {
        $document = Document::query()->find($documentId);
        if (! $document) {
            throw new CrmMcpAccessException('Document not found.', 404);
        }

        return $this->documentDownloadService->temporaryDownload($document, $staff);
    }

    private function findAccessibleRecord(int $adminId, Staff $staff): Admin
    {
        if ($adminId <= 0) {
            throw new CrmMcpAccessException('Invalid record id.', 422);
        }

        $record = Admin::query()
            ->with(['company:id,admin_id,company_name'])
            ->where('id', $adminId)
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->first();

        if (! $record) {
            throw new CrmMcpAccessException('Client or lead not found.', 404);
        }

        if (! StaffClientVisibility::canAccessClientOrLead($adminId, $staff)) {
            throw new CrmMcpAccessException('Unauthorized access to this record.', 403);
        }

        return $record;
    }

    /**
     * @return list<string>
     */
    private function normalizeRecordTypes(?string $type): array
    {
        $type = strtolower(trim((string) $type));

        return match ($type) {
            'client' => ['client'],
            'lead' => ['lead'],
            default => ['client', 'lead'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecordSummary(Admin $row, Staff $staff): array
    {
        $item = [
            'id' => (int) $row->id,
            'cid' => (int) $row->id,
            'type' => $row->type,
            'reference' => $row->client_id,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
            'email' => $row->email,
            'phone' => $row->phone,
            'country_code' => $row->country_code,
            'is_company' => (bool) $row->is_company,
            'company_name' => $row->company?->company_name,
            'lead_status' => $row->lead_status,
            'status' => $row->status,
        ];

        return StaffClientVisibility::enrichGlobalSearchItem($item, (string) $row->type, $staff);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRecordDetail(Admin $row): array
    {
        return [
            'id' => (int) $row->id,
            'type' => $row->type,
            'reference' => $row->client_id,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
            'email' => $row->email,
            'phone' => $row->phone,
            'country_code' => $row->country_code,
            'address' => $row->address,
            'city' => $row->city,
            'state' => $row->state,
            'country' => $row->country,
            'zip' => $row->zip,
            'dob' => optional($row->dob)?->toDateString(),
            'is_company' => (bool) $row->is_company,
            'company_name' => $row->company?->company_name,
            'lead_status' => $row->lead_status,
            'followup_date' => optional($row->followup_date)?->toIso8601String(),
            'status' => $row->status,
            'tagname' => $row->tagname,
            'is_archived' => (bool) $row->is_archived,
            'created_at' => optional($row->created_at)?->toIso8601String(),
            'updated_at' => optional($row->updated_at)?->toIso8601String(),
        ];
    }

    private function staffName(?Staff $staff): ?string
    {
        if (! $staff) {
            return null;
        }

        $name = trim(($staff->first_name ?? '').' '.($staff->last_name ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function audit(Staff $staff, string $action, array $context = []): void
    {
        Log::info('crm_mcp.'.$action, array_merge([
            'staff_id' => $staff->id,
            'staff_email' => $staff->email,
        ], $context));
    }
}
