<?php

namespace App\Services\EmailSync;

use App\Models\EmailLog;
use App\Models\Staff;
use App\Services\EmailMatchingService;
use App\Support\StaffClientVisibility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubjectReferenceAutoAssignService
{
    public function __construct(
        private EmailMatchingService $matchingService,
        private UnassignedEmailAssignmentService $assignmentService,
        private ManualUploadThreadMatchService $manualUploadMatchService,
    ) {
    }

    /**
     * Assign unassigned synced emails matched by client/matter refs, To/Cc/Bcc
     * client or lead emails, or a unique name in subject/body with one assignable matter.
     */
    public function assignMatchingUnassignedEmails(int $limit = 300): int
    {
        $result = $this->scanAndAssign(null, $limit, false, false);

        return (int) ($result['assigned_count'] ?? 0);
    }

    /**
     * Button-driven scan: return matches for staff to select; do not assign yet.
     *
     * @return array{
     *     assigned_count: int,
     *     assigned: list<array<string, mixed>>,
     *     ready_pairs: list<array<string, mixed>>,
     *     needs_matter: list<array<string, mixed>>,
     *     skipped_count: int
     * }
     */
    public function scanAndAssignForStaff(Staff $staff, int $limit = 400): array
    {
        return $this->scanAndAssign($staff, $limit, true, true, true);
    }

    /**
     * @param list<array{email_log_id: int, client_id: int, client_matter_id: int}> $items
     * @return array{success: bool, assigned_count: int, assigned: list<array<string, mixed>>, failed: list<array<string, mixed>>}
     */
    public function confirmMatterChoices(array $items, bool $enforceStaffAccess = true): array
    {
        $assigned = [];
        $failed = [];

        foreach ($items as $item) {
            $emailLogId = (int) ($item['email_log_id'] ?? 0);
            $clientId = (int) ($item['client_id'] ?? 0);
            $matterId = (int) ($item['client_matter_id'] ?? 0);
            if ($emailLogId < 1 || $clientId < 1 || $matterId < 1) {
                $failed[] = [
                    'email_log_id' => $emailLogId,
                    'message' => 'Client and matter are required.',
                ];
                continue;
            }

            try {
                $result = $this->assignmentService->assignToClient(
                    $emailLogId,
                    $clientId,
                    $matterId,
                    Auth::id() ?: null,
                    false,
                    'manual_assigned',
                    $enforceStaffAccess
                );
                if (! empty($result['success'])) {
                    $emailLog = EmailLog::find($emailLogId);
                    $assigned[] = $emailLog
                        ? $this->enrichAssignedRow($emailLog, 'matter_choice')
                        : [
                            'email_log_id' => $emailLogId,
                            'client_id' => $clientId,
                            'matter_id' => $matterId,
                            'matched_by' => 'matter_choice',
                        ];
                } else {
                    $failed[] = [
                        'email_log_id' => $emailLogId,
                        'message' => $result['message'] ?? 'Could not assign email.',
                    ];
                }
            } catch (Throwable $e) {
                $failed[] = [
                    'email_log_id' => $emailLogId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => $failed === [],
            'assigned_count' => count($assigned),
            'assigned' => $assigned,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{
     *     assigned_count: int,
     *     assigned: list<array<string, mixed>>,
     *     ready_pairs: list<array<string, mixed>>,
     *     needs_matter: list<array<string, mixed>>,
     *     skipped_count: int
     * }
     */
    protected function scanAndAssign(
        ?Staff $staff,
        int $limit,
        bool $collectNeedsMatter,
        bool $enforceStaffAccess,
        bool $previewOnly = false
    ): array {
        $query = EmailLog::query()
            ->where('sync_assignment_status', 'unassigned')
            ->where(function ($clientQuery) {
                $clientQuery->whereNull('client_id')
                    ->orWhere('client_id', 0);
            });

        IncomingEmailSyncService::applyUnassignedSyncedInboxScope($query);
        $query->where('sync_assignment_status', 'unassigned');
        EmailLog::applyExcludeCalendarInvitesFromMailLists($query);

        if ($staff) {
            IncomingEmailSyncService::applySyncedInboxVisibilityFilter($query, $staff);
        }

        $assigned = [];
        $readyPairs = [];
        $needsMatterByClient = [];
        $skipped = 0;
        $processed = 0;

        $query->orderBy('id')->chunkById(40, function ($emailLogs) use (
            &$assigned,
            &$readyPairs,
            &$needsMatterByClient,
            &$skipped,
            &$processed,
            $limit,
            $collectNeedsMatter,
            $enforceStaffAccess,
            $previewOnly,
            $staff
        ) {
            foreach ($emailLogs as $emailLog) {
                if ($processed >= $limit) {
                    return false;
                }
                $processed++;

                $subject = (string) ($emailLog->subject ?? '');
                $bodyPreview = mb_substr(strip_tags((string) ($emailLog->message ?? '')), 0, 2000);
                $text = $subject . "\n" . $bodyPreview;

                // Prefer matching against manually uploaded matter emails (same conversation thread).
                $manualMatch = $this->manualUploadMatchService->findMatterMatches($emailLog);
                if ($manualMatch && empty($manualMatch['ambiguous']) && ! empty($manualMatch['client_matter_id'])) {
                    $clientId = (int) $manualMatch['client_id'];
                    $matterId = (int) $manualMatch['client_matter_id'];
                    if ($staff && ! StaffClientVisibility::canAccessClientOrLead($clientId, $staff)) {
                        $skipped++;
                        continue;
                    }

                    if ($previewOnly) {
                        $readyPairs[] = array_merge(
                            $this->assignedRow(
                                $emailLog,
                                $manualMatch,
                                $matterId,
                                'manual_upload_thread'
                            ),
                            [
                                'client_matter_id' => $matterId,
                                'matched_manual_emails' => $manualMatch['matched_manual_emails'] ?? [],
                            ]
                        );
                        continue;
                    }

                    $result = $this->tryAssign(
                        $emailLog,
                        $clientId,
                        $matterId,
                        'auto_assigned',
                        $enforceStaffAccess
                    );
                    if ($result) {
                        $assigned[] = array_merge(
                            $this->assignedRow(
                                $emailLog->fresh() ?: $emailLog,
                                $manualMatch,
                                $matterId,
                                'manual_upload_thread'
                            ),
                            [
                                'matched_manual_emails' => $manualMatch['matched_manual_emails'] ?? [],
                            ]
                        );
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                if ($manualMatch && ! empty($manualMatch['ambiguous']) && $collectNeedsMatter) {
                    $clientId = (int) ($manualMatch['client_id'] ?? 0);
                    if ($clientId > 0 && (! $staff || StaffClientVisibility::canAccessClientOrLead($clientId, $staff))) {
                        $matters = $this->matchingService->listMattersForClient($clientId);
                        $mattersForChoice = $this->matchingService->mattersForStaffChoice($matters);
                        if ($mattersForChoice !== []) {
                            if (! isset($needsMatterByClient[$clientId])) {
                                $needsMatterByClient[$clientId] = [
                                    'client_id' => $clientId,
                                    'client_ref' => $manualMatch['client_ref'] ?? '',
                                    'client_name' => $manualMatch['client_name'] ?? '',
                                    'matched_by' => 'manual_upload_thread',
                                    'matters' => $mattersForChoice,
                                    'emails' => [],
                                ];
                            }
                            $needsMatterByClient[$clientId]['emails'][] = [
                                'email_log_id' => (int) $emailLog->id,
                                'subject' => $subject,
                                'from_mail' => (string) ($emailLog->from_mail ?? ''),
                                'matched_by' => 'manual_upload_thread',
                                'matched_manual_emails' => $manualMatch['matched_manual_emails'] ?? [],
                            ];
                            continue;
                        }
                    }
                }

                $pair = $subject !== ''
                    ? $this->matchingService->findUniqueClientMatterAssignment($text)
                    : null;
                if ($pair && ! empty($pair['client_id']) && ! empty($pair['client_matter_id'])) {
                    if ($staff && ! StaffClientVisibility::canAccessClientOrLead((int) $pair['client_id'], $staff)) {
                        $skipped++;
                        continue;
                    }

                    if ($previewOnly) {
                        $readyPairs[] = array_merge(
                            $this->assignedRow(
                                $emailLog,
                                $pair,
                                (int) $pair['client_matter_id'],
                                'client_matter_pair'
                            ),
                            [
                                'client_matter_id' => (int) $pair['client_matter_id'],
                            ]
                        );
                        continue;
                    }

                    $result = $this->tryAssign(
                        $emailLog,
                        (int) $pair['client_id'],
                        (int) $pair['client_matter_id'],
                        'auto_assigned',
                        $enforceStaffAccess
                    );
                    if ($result) {
                        $assigned[] = $this->assignedRow(
                            $emailLog->fresh() ?: $emailLog,
                            $pair,
                            (int) $pair['client_matter_id'],
                            'client_matter_pair'
                        );
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $client = null;
                $matchedBy = '';

                $recipientClient = $this->matchingService->findUniqueClientByRecipientEmails([
                    'to_mail' => (string) ($emailLog->to_mail ?? ''),
                    'cc' => (string) ($emailLog->cc ?? ''),
                    'bcc' => (string) ($emailLog->bcc ?? ''),
                ]);
                if ($recipientClient) {
                    $client = $recipientClient;
                    $matchedBy = 'recipient_email';
                }

                if (! $client && $text !== "\n") {
                    $refClient = $this->matchingService->findUniqueClientByReference($text);
                    if ($refClient) {
                        $client = $refClient;
                        $matchedBy = 'client_id';
                    }
                }

                if (! $client && $text !== "\n") {
                    if ($this->matchingService->extractClientReferences($text) !== []) {
                        $skipped++;
                        continue;
                    }
                    $nameClient = $this->matchingService->findUniqueClientByName($text);
                    if ($nameClient) {
                        $client = $nameClient;
                        $matchedBy = 'client_name';
                    }
                }

                if (! $client) {
                    $skipped++;
                    continue;
                }

                $clientId = (int) $client['client_id'];
                if ($staff && ! StaffClientVisibility::canAccessClientOrLead($clientId, $staff)) {
                    $skipped++;
                    continue;
                }

                $matters = $this->matchingService->listMattersForClient($clientId);
                if ($matters === []) {
                    $skipped++;
                    continue;
                }

                // Exactly one active matter (or only one matter total) → auto-assign.
                // Multiple active matters → manual (needs_matter when collecting).
                $uniqueMatter = $this->matchingService->resolveUniqueAssignableMatter($matters);
                if ($uniqueMatter !== null) {
                    $matterId = (int) ($uniqueMatter['id'] ?? 0);
                    if ($matterId < 1) {
                        $skipped++;
                        continue;
                    }

                    if ($previewOnly) {
                        $readyPairs[] = array_merge(
                            $this->assignedRow(
                                $emailLog,
                                array_merge($client, [
                                    'matter_no' => (string) ($uniqueMatter['matter_no'] ?? ''),
                                    'matter_title' => (string) ($uniqueMatter['matter_title'] ?? ''),
                                ]),
                                $matterId,
                                $matchedBy
                            ),
                            [
                                'client_matter_id' => $matterId,
                            ]
                        );
                        continue;
                    }

                    $result = $this->tryAssign(
                        $emailLog,
                        $clientId,
                        $matterId,
                        'auto_assigned',
                        $enforceStaffAccess
                    );
                    if ($result) {
                        $assigned[] = $this->assignedRow(
                            $emailLog->fresh() ?: $emailLog,
                            array_merge($client, [
                                'matter_no' => (string) ($uniqueMatter['matter_no'] ?? ''),
                                'matter_title' => (string) ($uniqueMatter['matter_title'] ?? ''),
                            ]),
                            $matterId,
                            $matchedBy
                        );
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                if (! $collectNeedsMatter) {
                    $skipped++;
                    continue;
                }

                $mattersForChoice = $this->matchingService->mattersForStaffChoice($matters);
                if (! isset($needsMatterByClient[$clientId])) {
                    $needsMatterByClient[$clientId] = [
                        'client_id' => $clientId,
                        'client_ref' => $client['client_ref'] ?? '',
                        'client_name' => $client['client_name'] ?? '',
                        'matched_by' => $matchedBy,
                        'matters' => $mattersForChoice,
                        'emails' => [],
                    ];
                }

                $needsMatterByClient[$clientId]['emails'][] = [
                    'email_log_id' => (int) $emailLog->id,
                    'subject' => $subject,
                    'from_mail' => (string) ($emailLog->from_mail ?? ''),
                    'matched_by' => $matchedBy,
                ];
            }

            return $processed < $limit;
        });

        return [
            'assigned_count' => count($assigned),
            'assigned' => $assigned,
            'ready_pairs' => $readyPairs,
            'needs_matter' => array_values($needsMatterByClient),
            'skipped_count' => $skipped,
        ];
    }

    protected function tryAssign(
        EmailLog $emailLog,
        int $clientId,
        int $clientMatterId,
        string $status,
        bool $enforceStaffAccess
    ): bool {
        try {
            $result = $this->assignmentService->assignToClient(
                (int) $emailLog->id,
                $clientId,
                $clientMatterId,
                Auth::id() ?: null,
                false,
                $status,
                $enforceStaffAccess
            );

            return ! empty($result['success']);
        } catch (Throwable $e) {
            Log::warning('Subject-reference auto-assign failed', [
                'email_log_id' => $emailLog->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $match
     * @return array<string, mixed>
     */
    protected function assignedRow(EmailLog $emailLog, array $match, int $matterId, string $matchedBy): array
    {
        return [
            'email_log_id' => (int) $emailLog->id,
            'subject' => (string) ($emailLog->subject ?? ''),
            'from_mail' => (string) ($emailLog->from_mail ?? ''),
            'client_id' => (int) ($match['client_id'] ?? $emailLog->client_id ?? 0),
            'client_ref' => (string) ($match['client_ref'] ?? ''),
            'client_name' => (string) ($match['client_name'] ?? ''),
            'matter_id' => $matterId,
            'matter_no' => (string) ($match['matter_no'] ?? ''),
            'matter_title' => (string) ($match['matter_title'] ?? ''),
            'matched_by' => $matchedBy,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function enrichAssignedRow(EmailLog $emailLog, string $matchedBy): array
    {
        $client = \App\Models\Admin::query()
            ->select('id', 'client_id', 'first_name', 'last_name', 'email', 'type')
            ->find((int) $emailLog->client_id);
        $matter = \App\Models\ClientMatter::query()
            ->leftJoin('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
            ->where('client_matters.id', (int) $emailLog->client_matter_id)
            ->select(
                'client_matters.id',
                'client_matters.client_unique_matter_no',
                'matters.title as matter_title'
            )
            ->first();

        $summary = $client ? $this->matchingService->clientSummary($client) : [
            'client_id' => (int) $emailLog->client_id,
            'client_ref' => '',
            'client_name' => '',
        ];

        return $this->assignedRow(
            $emailLog,
            array_merge($summary, [
                'matter_no' => (string) ($matter->client_unique_matter_no ?? ''),
                'matter_title' => (string) ($matter->matter_title ?? ''),
            ]),
            (int) $emailLog->client_matter_id,
            $matchedBy
        );
    }
}
