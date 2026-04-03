<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\StaffClientVisibility;
use App\Traits\ClientAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * Generic controller for visa-type sheets (TR, Visitor, Student, PR, etc.).
 * Config-driven via config/sheets/visa_types.php.
 */
class VisaTypeSheetController extends Controller
{
    use ClientAuthorization;

    public const TABS = ['ongoing', 'lodged', 'checklist', 'discontinue'];

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Display the visa type sheet with 4 tabs.
     */
    public function index(Request $request, string $visaType, $tab = null)
    {
        $config = $this->getVisaTypeConfig($visaType);
        if (!$config) {
            abort(404, "Visa type sheet '{$visaType}' not configured.");
        }

        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet($visaType)) {
            abort(403, 'Unauthorized');
        }

        $tab = $tab ?? $request->input('tab', 'ongoing');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'ongoing';
        }

        $tabConfig = $this->getTabConfig($visaType, $tab);
        $sessionKey = $tabConfig['session_key'];

        $setupRequired = $this->isSetupRequired($config);

        if ($request->has('clear_filters')) {
            session()->forget($sessionKey);
            return redirect()->route($config['route'], ['visaType' => $visaType, 'tab' => $tab]);
        }

        $request->merge($this->getFiltersFromSession($request, $sessionKey));

        // Default to 'all' so Ongoing and other tabs show all matters when no filter is set.
        // Previously defaulted to 'me' which hid records not assigned to the current staff member.
        if (!$request->has('assignee') || $request->input('assignee') === '') {
            $request->merge(['assignee' => 'all']);
        }

        $perPage = (int) $request->get('per_page', 50);
        $allowedPerPage = [10, 25, 50, 100, 200];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 50;
        }

        $this->persistFiltersToSession($request, $sessionKey);

        if ($setupRequired) {
            $rows = new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => route($config['route'], ['visaType' => $visaType]),
                'pageName' => 'page',
            ]);
            $rows->appends(array_merge($request->except('page'), ['tab' => $tab]));
        } else {
            if ($tab === 'checklist') {
                $rows = $this->buildChecklistTabWithLeads($request, $config, $perPage, $visaType);
            } else {
                $query = $this->buildBaseQuery($request, $tab, $config);
                $query = $this->applyFilters($query, $request, $config);
                $query = $this->applySorting($query, $request, $tab, $config);

                $rows = $query->paginate($perPage)->appends(array_merge($request->except('page'), ['tab' => $tab]));
            }

            $rows->appends(array_merge($request->except('page'), ['tab' => $tab]));

            $rows->getCollection()->transform(function ($row) {
                $row->is_lead = $row->is_lead ?? false;
                if (!$row->is_lead && isset($row->matter_internal_id)) {
                    $payments = $this->calculatePaymentsForMatter($row->client_id, $row->matter_internal_id);
                    $row->total_payment = $payments['total'];
                    $row->pending_payment = $payments['pending'];
                } else {
                    $row->total_payment = 0;
                    $row->pending_payment = 0;
                }
                return $row;
            });
        }

        $branches = Branch::orderBy('office_name')->get(['id', 'office_name']);
        $assignees = $this->getAssignees();
        $currentStages = $this->getCurrentStagesForTab($tab, $config);
        $matterTypes = $this->getMatterTypesForVisaType($config);
        $activeFilterCount = $this->countActiveFilters($request);

        return view('crm.clients.sheets.visa-type-sheet', compact(
            'rows',
            'tab',
            'perPage',
            'activeFilterCount',
            'branches',
            'assignees',
            'currentStages',
            'matterTypes',
            'config',
            'tabConfig',
            'visaType',
            'setupRequired'
        ));
    }

    protected function getVisaTypeConfig(string $visaType): ?array
    {
        $configs = config('sheets.visa_types', []);
        return $configs[$visaType] ?? null;
    }

    protected function isSetupRequired(array $config): bool
    {
        $refTable = $config['reference_table'] ?? '';
        $remindersTable = $config['reminders_table'] ?? '';
        $checklistCol = $config['checklist_status_column'] ?? '';

        return !Schema::hasTable($refTable)
            || !Schema::hasColumn('client_matters', $checklistCol)
            || !Schema::hasTable($remindersTable);
    }

    protected function getTabConfig(string $visaType, string $tab): array
    {
        $config = $this->getVisaTypeConfig($visaType);
        $prefix = $config['session_prefix'] ?? $visaType . '_sheet_';

        $titles = [
            'ongoing' => 'Ongoing',
            'lodged' => 'Lodged',
            'checklist' => 'Checklist',
            'discontinue' => 'Discontinue',
        ];

        return [
            'title' => $titles[$tab] ?? 'Ongoing',
            'session_key' => $prefix . $tab . '_filters',
        ];
    }

    protected function getFiltersFromSession(Request $request, string $sessionKey): array
    {
        $filterParams = ['branch', 'assignee', 'current_stage', 'visa_expiry_from', 'visa_expiry_to', 'deadline_from', 'deadline_to', 'matter_type', 'search', 'per_page'];
        foreach ($filterParams as $key) {
            $val = $request->input($key);
            if ($request->has($key) && $val !== null && $val !== '' && (!is_array($val) || !empty($val))) {
                return [];
            }
        }
        return session($sessionKey, []);
    }

    protected function persistFiltersToSession(Request $request, string $sessionKey): void
    {
        $payload = array_filter([
            'branch' => $request->input('branch'),
            'assignee' => $request->input('assignee'),
            'current_stage' => $request->input('current_stage'),
            'visa_expiry_from' => $request->input('visa_expiry_from'),
            'visa_expiry_to' => $request->input('visa_expiry_to'),
            'deadline_from' => $request->input('deadline_from'),
            'deadline_to' => $request->input('deadline_to'),
            'matter_type' => $request->input('matter_type'),
            'search' => $request->input('search'),
            'per_page' => $request->input('per_page'),
        ], function ($v) {
            if (is_array($v)) {
                return !empty($v);
            }
            return $v !== null && $v !== '';
        });
        session()->put($sessionKey, $payload);
    }

    protected function getAssignees()
    {
        $allIds = DB::table('client_matters')
            ->select('sel_migration_agent')
            ->whereNotNull('sel_migration_agent')
            ->distinct()
            ->pluck('sel_migration_agent')
            ->merge(
                DB::table('client_matters')->select('sel_person_responsible')->whereNotNull('sel_person_responsible')->distinct()->pluck('sel_person_responsible')
            )
            ->merge(
                DB::table('client_matters')->select('sel_person_assisting')->whereNotNull('sel_person_assisting')->distinct()->pluck('sel_person_assisting')
            )
            ->unique()
            ->filter()
            ->values();
        $assignees = \App\Models\Staff::where('status', 1)
            ->whereIn('id', $allIds)
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);
        $currentUser = Auth::user();
        if ($currentUser && $assignees->pluck('id')->doesntContain($currentUser->id)) {
            $assignees->push($currentUser);
            $assignees = $assignees->sortBy(fn ($a) => trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')))->values();
        }
        return $assignees;
    }

    protected function getCurrentStagesForTab(string $tab, array $config)
    {
        $key = match ($tab) {
            'lodged' => 'lodged_stages',
            'discontinue' => 'discontinue_stages',
            'checklist' => 'checklist_early_stages',
            default => 'ongoing_stages',
        };
        $stages = $config[$key] ?? [];
        if (!is_array($stages)) {
            $stages = [];
        }
        return collect($stages)->filter(fn ($s) => $s !== null && trim((string) $s) !== '')
            ->values()->mapWithKeys(fn ($s) => [trim((string) $s) => trim((string) $s)]);
    }

    /**
     * Get distinct matter types (titles) for the visa type, for filter dropdown.
     */
    protected function getMatterTypesForVisaType(array $config): array
    {
        $matterCondition = $this->getMatterCondition($config);
        $titles = DB::table('matters as m')
            ->whereRaw($matterCondition)
            ->whereNotNull('m.title')
            ->where('m.title', '!=', '')
            ->distinct()
            ->orderBy('m.title')
            ->pluck('m.title')
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values()
            ->all();
        return array_combine($titles, $titles) ?: [];
    }

    /**
     * Build Checklist tab results including both client matters and leads.
     */
    protected function buildChecklistTabWithLeads(Request $request, array $config, int $perPage, string $visaType = ''): \Illuminate\Pagination\LengthAwarePaginator
    {
        $refTable = $config['reference_table'];
        $refAlias = $config['reference_alias'];
        $refType = $config['reference_type'] ?? $visaType;
        $checklistCol = $config['checklist_status_column'];
        $leadRefTable = $config['lead_reference_table'] ?? null;
        $remindersTable = $config['reminders_table'] ?? null;
        $leadRemindersTable = $config['lead_reminders_table'] ?? null;
        $matterCondition = $this->getMatterCondition($config);

        // Client matters with workflow=Checklist
        $clientQuery = DB::table('client_matters as cm')
            ->join('matters as m', 'm.id', '=', 'cm.sel_matter_id')
            ->leftJoin("{$refTable} as {$refAlias}", function ($j) use ($refAlias, $refType, $refTable) {
                $j->on("{$refAlias}.client_id", '=', 'cm.client_id')
                    ->on("{$refAlias}.client_matter_id", '=', 'cm.id');
                if (!empty($refType) && $refTable === 'client_matter_references') {
                    $j->where("{$refAlias}.type", '=', $refType);
                }
            })
            ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
            ->join('admins', 'cm.client_id', '=', 'admins.id')
            ->leftJoin('staff as agent', 'cm.sel_migration_agent', '=', 'agent.id')
            ->leftJoin('branches', 'cm.office_id', '=', 'branches.id')
            ->whereRaw($matterCondition)
            ->whereRaw('cm.matter_status = 1')
            ->whereRaw("LOWER(TRIM(COALESCE(ws.name, ''))) = 'checklist'")
            ->where(function ($q) use ($checklistCol) {
                $q->whereNull("cm.{$checklistCol}")
                    ->orWhereIn("cm.{$checklistCol}", ['active', 'hold']);
            })
            ->where('admins.is_archived', 0)
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted')
            ->where(function ($q) {
                $q->whereNull('admins.type')->orWhere('admins.type', '!=', 'lead');
            });

        // Person Assisting role: restrict to matters where they are MA / PR / PA
        if ($paId = StaffClientVisibility::personAssistingStaffIdOrNull(Auth::user())) {
            $clientQuery->where(function ($q) use ($paId) {
                $q->where('cm.sel_migration_agent', $paId)
                    ->orWhere('cm.sel_person_responsible', $paId)
                    ->orWhere('cm.sel_person_assisting', $paId);
            });
        }

        $clientQuery->select(
                'cm.id as matter_internal_id',
                'cm.client_id',
                'admins.client_id as crm_ref',
                'admins.first_name',
                'admins.last_name',
                'admins.dob',
                DB::raw('admins."visaExpiry" as visa_expiry'),
                'cm.client_unique_matter_no',
                'm.title as matter_title',
                'cm.deadline',
                'cm.other_reference',
                'cm.department_reference',
                'cm.office_id',
                'cm.sel_migration_agent as assignee_id',
                DB::raw("CONCAT(COALESCE(agent.first_name, ''), ' ', COALESCE(agent.last_name, '')) as assignee_name"),
                'branches.office_name as branch_name',
                'ws.name as application_stage',
                "{$refAlias}.current_status",
                "{$refAlias}.payment_display_note",
                "{$refAlias}.comments as sheet_comment_text",
                "{$refAlias}.checklist_sent_at",
                "{$refAlias}.is_pinned",
                DB::raw("COALESCE(cm.{$checklistCol}, 'active') as tr_checklist_status"),
                DB::raw('0 as is_lead')
            );
        $this->applyFilters($clientQuery, $request, $config, 'cm');
        $clientRows = $clientQuery->get();

        $leadRows = collect();
        if ($leadRefTable && Schema::hasTable($leadRefTable)) {
            $matterIds = DB::table(DB::raw('matters as m'))->whereRaw($matterCondition)->pluck('id');
            if ($matterIds->isNotEmpty()) {
                $leadQuery = DB::table($leadRefTable . ' as lr')
                    ->where('lr.type', $refType)
                    ->join('admins as a', 'lr.lead_id', '=', 'a.id')
                    ->join('matters as m', 'lr.matter_id', '=', 'm.id')
                    ->whereIn('lr.matter_id', $matterIds)
                    ->where('a.is_archived', 0)
                    ->where('a.type', 'lead')
                    ->whereNull('a.is_deleted')
                    ->select(
                        DB::raw('NULL as matter_internal_id'),
                        'lr.lead_id as client_id',
                        'a.client_id as crm_ref',
                        'a.first_name',
                        'a.last_name',
                        'a.dob',
                        DB::raw('a."visaExpiry" as visa_expiry'),
                        DB::raw("CONCAT('Lead - ', COALESCE(m.title, '')) as client_unique_matter_no"),
                        'm.title as matter_title',
                        DB::raw('NULL as deadline'),
                        DB::raw('NULL as other_reference'),
                        DB::raw('NULL as department_reference'),
                        DB::raw('NULL as office_id'),
                        DB::raw('NULL as assignee_id'),
                        DB::raw("'' as assignee_name"),
                        DB::raw('NULL as branch_name'),
                        DB::raw("'Lead' as application_stage"),
                        DB::raw('NULL as current_status'),
                        DB::raw('NULL as payment_display_note'),
                        DB::raw('NULL as sheet_comment_text'),
                        'lr.checklist_sent_at',
                        DB::raw('false as is_pinned'),
                        DB::raw("'active' as tr_checklist_status"),
                        DB::raw('1 as is_lead')
                    );
                // Person Assisting: restrict leads to those assigned to them
                if ($paId = StaffClientVisibility::personAssistingStaffIdOrNull(Auth::user())) {
                    $leadQuery->where('a.user_id', $paId);
                }

                if ($request->filled('search')) {
                    $search = '%' . strtolower($request->input('search')) . '%';
                    $leadQuery->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(a.first_name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(a.last_name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(a.client_id) LIKE ?', [$search]);
                    });
                }
                if ($request->filled('matter_type')) {
                    $val = $request->input('matter_type');
                    $leadQuery->whereRaw('LOWER(m.title) LIKE ?', ['%' . strtolower($val) . '%']);
                }
                $leadRows = $leadQuery->get();
            }
        }

        foreach ($clientRows as $r) {
            $r->is_lead = 0;
        }
        foreach ($leadRows as $r) {
            $r->is_lead = 1;
        }

        $all = $clientRows->concat($leadRows);
        if ($remindersTable && Schema::hasTable($remindersTable)) {
            foreach ($all as $row) {
                if ($row->is_lead) {
                    if ($leadRemindersTable && Schema::hasTable($leadRemindersTable)) {
                        $row->email_reminder_latest = DB::table($leadRemindersTable)->where('visa_type', $refType)->where('lead_id', $row->client_id)->where('type', 'email')->max('reminded_at');
                        $row->email_reminder_count = DB::table($leadRemindersTable)->where('visa_type', $refType)->where('lead_id', $row->client_id)->where('type', 'email')->count();
                        $row->sms_reminder_latest = DB::table($leadRemindersTable)->where('visa_type', $refType)->where('lead_id', $row->client_id)->where('type', 'sms')->max('reminded_at');
                        $row->sms_reminder_count = DB::table($leadRemindersTable)->where('visa_type', $refType)->where('lead_id', $row->client_id)->where('type', 'sms')->count();
                        $row->phone_reminder_latest = DB::table($leadRemindersTable)->where('visa_type', $refType)->where('lead_id', $row->client_id)->where('type', 'phone')->max('reminded_at');
                        $row->phone_reminder_count = DB::table($leadRemindersTable)->where('visa_type', $refType)->where('lead_id', $row->client_id)->where('type', 'phone')->count();
                    } else {
                        $row->email_reminder_latest = $row->email_reminder_count = $row->sms_reminder_latest = $row->sms_reminder_count = $row->phone_reminder_latest = $row->phone_reminder_count = null;
                    }
                } else {
                    $row->email_reminder_latest = DB::table($remindersTable)->where('visa_type', $refType)->where('client_matter_id', $row->matter_internal_id)->where('type', 'email')->max('reminded_at');
                    $row->email_reminder_count = DB::table($remindersTable)->where('visa_type', $refType)->where('client_matter_id', $row->matter_internal_id)->where('type', 'email')->count();
                    $row->sms_reminder_latest = DB::table($remindersTable)->where('visa_type', $refType)->where('client_matter_id', $row->matter_internal_id)->where('type', 'sms')->max('reminded_at');
                    $row->sms_reminder_count = DB::table($remindersTable)->where('visa_type', $refType)->where('client_matter_id', $row->matter_internal_id)->where('type', 'sms')->count();
                    $row->phone_reminder_latest = DB::table($remindersTable)->where('visa_type', $refType)->where('client_matter_id', $row->matter_internal_id)->where('type', 'phone')->max('reminded_at');
                    $row->phone_reminder_count = DB::table($remindersTable)->where('visa_type', $refType)->where('client_matter_id', $row->matter_internal_id)->where('type', 'phone')->count();
                }
            }
        }
        $total = $all->count();
        $page = (int) $request->get('page', 1);
        $slice = $all->slice(($page - 1) * $perPage, $perPage)->values();
        $visaTypeParam = $request->route('visaType');
        $path = route($config['route'], ['visaType' => $visaTypeParam]);
        return new \Illuminate\Pagination\LengthAwarePaginator($slice, $total, $perPage, $page, ['path' => $path, 'pageName' => 'page']);
    }

    protected function getMatterCondition(array $config): string
    {
        $nickNames = $config['matter_nick_names'] ?? [];
        $patterns = $config['matter_title_patterns'] ?? [];
        $cond = [];
        foreach ($nickNames as $n) {
            $cond[] = "LOWER(COALESCE(m.nick_name, '')) = '" . addslashes(strtolower($n)) . "'";
        }
        foreach ($patterns as $p) {
            $cond[] = "LOWER(COALESCE(m.title, '')) LIKE '%" . addslashes(strtolower($p)) . "%'";
        }
        return $cond ? '(' . implode(' OR ', $cond) . ')' : '1 = 0';
    }

    protected function buildBaseQuery(Request $request, string $tab, array $config)
    {
        $matterCondition = $this->getMatterCondition($config);
        $refTable = $config['reference_table'];
        $refAlias = $config['reference_alias'];
        $visaType = $request->route('visaType', '');
        $refType = $config['reference_type'] ?? $visaType;
        $checklistCol = $config['checklist_status_column'];

        // One row per matter (per plan: "one row per TR matter — filtered by active tab").
        // Show ALL matters matching the visa type and tab, not just latest per client.
        $baseMattersSql = "
            SELECT
                cm.id AS matter_id,
                cm.client_id,
                cm.client_unique_matter_no,
                cm.other_reference,
                cm.department_reference,
                cm.sel_migration_agent,
                cm.sel_person_responsible,
                cm.sel_person_assisting,
                cm.office_id,
                cm.workflow_stage_id,
                cm.decision_outcome,
                cm.decision_note,
                cm.matter_status,
                cm.deadline,
                cm.{$checklistCol} as checklist_status,
                m.title as matter_title
            FROM client_matters cm
            INNER JOIN matters m ON m.id = cm.sel_matter_id
            WHERE {$matterCondition}
        ";

        $query = DB::table(DB::raw('(' . $baseMattersSql . ') AS latest_matter'))
            ->leftJoin("{$refTable} as {$refAlias}", function ($join) use ($refAlias, $refType, $refTable) {
                $join->on("{$refAlias}.client_id", '=', 'latest_matter.client_id')
                    ->on("{$refAlias}.client_matter_id", '=', 'latest_matter.matter_id');
                if (!empty($refType) && $refTable === 'client_matter_references') {
                    $join->where("{$refAlias}.type", '=', $refType);
                }
            })
            ->leftJoin('workflow_stages as ws', 'latest_matter.workflow_stage_id', '=', 'ws.id')
            ->join('admins', 'latest_matter.client_id', '=', 'admins.id')
            ->leftJoin('staff as agent', 'latest_matter.sel_migration_agent', '=', 'agent.id')
            ->leftJoin('branches', 'latest_matter.office_id', '=', 'branches.id')
            ->select(
                'latest_matter.matter_id as matter_internal_id',
                'latest_matter.client_id',
                'admins.client_id as crm_ref',
                'admins.first_name',
                'admins.last_name',
                'admins.dob',
                DB::raw('admins."visaExpiry" as visa_expiry'),
                'latest_matter.client_unique_matter_no',
                'latest_matter.matter_title',
                'latest_matter.deadline',
                'latest_matter.other_reference',
                'latest_matter.department_reference',
                'latest_matter.office_id',
                'latest_matter.sel_migration_agent as assignee_id',
                DB::raw("CONCAT(COALESCE(agent.first_name, ''), ' ', COALESCE(agent.last_name, '')) as assignee_name"),
                'branches.office_name as branch_name',
                'ws.name as application_stage',
                "{$refAlias}.current_status",
                "{$refAlias}.payment_display_note",
                "{$refAlias}.institute_override",
                "{$refAlias}.comments as sheet_comment_text",
                "{$refAlias}.checklist_sent_at",
                "{$refAlias}.is_pinned",
                'latest_matter.checklist_status as tr_checklist_status',
                'latest_matter.decision_outcome',
                'latest_matter.decision_note'
            )
            ->where('admins.is_archived', 0)
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted');

        // Person Assisting role: restrict to matters where they are MA / PR / PA
        if ($paId = StaffClientVisibility::personAssistingStaffIdOrNull(Auth::user())) {
            $query->where(function ($q) use ($paId) {
                $q->where('latest_matter.sel_migration_agent', $paId)
                    ->orWhere('latest_matter.sel_person_responsible', $paId)
                    ->orWhere('latest_matter.sel_person_assisting', $paId);
            });
        }

        $this->applyTabFilter($query, $tab, $config);

        return $query;
    }

    protected function applyTabFilter($query, string $tab, array $config): void
    {
        $ongoingStages = array_map('strtolower', $config['ongoing_stages'] ?? []);
        $lodgedStages = array_map('strtolower', $config['lodged_stages'] ?? []);
        $checklistStages = array_map('strtolower', $config['checklist_early_stages'] ?? []);
        $discontinueStages = array_map('strtolower', $config['discontinue_stages'] ?? []);
        $excludedForOngoing = array_merge($lodgedStages, $checklistStages, $discontinueStages);

        if ($tab === 'discontinue') {
            $query->where(function ($q) use ($discontinueStages) {
                $q->whereRaw('latest_matter.matter_status = 0');
                if (!empty($discontinueStages)) {
                    $ph = implode(',', array_fill(0, count($discontinueStages), '?'));
                    $q->orWhereRaw('LOWER(TRIM(ws.name)) IN (' . $ph . ')', $discontinueStages);
                }
            });
        } elseif ($tab === 'lodged') {
            $query->whereRaw('latest_matter.matter_status = 1');
            if (!empty($lodgedStages)) {
                $ph = implode(',', array_fill(0, count($lodgedStages), '?'));
                $query->whereRaw('LOWER(TRIM(ws.name)) IN (' . $ph . ')', $lodgedStages);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($tab === 'checklist') {
            $query->whereRaw('latest_matter.matter_status = 1');
            if (!empty($checklistStages)) {
                $ph = implode(',', array_fill(0, count($checklistStages), '?'));
                $query->whereRaw('LOWER(TRIM(ws.name)) IN (' . $ph . ')', $checklistStages);
            } else {
                $query->whereRaw('1 = 0');
            }
            $query->where(function ($q) {
                $q->whereNull('latest_matter.checklist_status')
                    ->orWhereIn('latest_matter.checklist_status', ['active', 'hold']);
            });
        } else {
            $query->whereRaw('latest_matter.matter_status = 1');
            if (!empty($excludedForOngoing)) {
                $ph = implode(',', array_fill(0, count($excludedForOngoing), '?'));
                $query->whereRaw('(LOWER(TRIM(ws.name)) NOT IN (' . $ph . ') OR ws.name IS NULL)', $excludedForOngoing);
            }
        }
    }

    /**
     * Apply filters to a visa sheet query.
     *
     * @param string $matterAlias Table alias for the matter columns (e.g. 'latest_matter' for buildBaseQuery subquery, 'cm' for buildChecklistTabWithLeads client query)
     */
    protected function applyFilters($query, Request $request, array $config, string $matterAlias = 'latest_matter')
    {
        $refAlias = $config['reference_alias'] ?? 'ref';

        if ($request->filled('branch')) {
            $branchIds = is_array($request->input('branch')) ? $request->input('branch') : [$request->input('branch')];
            $query->whereIn("{$matterAlias}.office_id", $branchIds);
        }
        if ($request->filled('assignee') && $request->input('assignee') !== 'all') {
            $assigneeId = $request->input('assignee') === 'me' ? Auth::id() : $request->input('assignee');
            if ($assigneeId) {
                $query->where(function ($q) use ($assigneeId, $matterAlias) {
                    $q->where("{$matterAlias}.sel_migration_agent", $assigneeId)
                        ->orWhere("{$matterAlias}.sel_person_responsible", $assigneeId)
                        ->orWhere("{$matterAlias}.sel_person_assisting", $assigneeId);
                });
            }
        }
        if ($request->filled('current_stage')) {
            $query->where('ws.name', $request->input('current_stage'));
        }
        if ($request->filled('visa_expiry_from')) {
            try {
                $from = Carbon::createFromFormat('d/m/Y', $request->input('visa_expiry_from'))->startOfDay();
                $query->whereRaw('admins."visaExpiry" >= ?', [$from]);
            } catch (\Exception $e) {}
        }
        if ($request->filled('visa_expiry_to')) {
            try {
                $to = Carbon::createFromFormat('d/m/Y', $request->input('visa_expiry_to'))->endOfDay();
                $query->whereRaw('admins."visaExpiry" <= ?', [$to]);
            } catch (\Exception $e) {}
        }
        if ($request->filled('deadline_from')) {
            try {
                $from = Carbon::createFromFormat('d/m/Y', $request->input('deadline_from'))->startOfDay();
                $query->whereRaw("{$matterAlias}.deadline >= ?", [$from]);
            } catch (\Exception $e) {}
        }
        if ($request->filled('deadline_to')) {
            try {
                $to = Carbon::createFromFormat('d/m/Y', $request->input('deadline_to'))->endOfDay();
                $query->whereRaw("{$matterAlias}.deadline <= ?", [$to]);
            } catch (\Exception $e) {}
        }
        if ($request->filled('matter_type')) {
            $val = $request->input('matter_type');
            $matterTitleCol = $matterAlias === 'latest_matter' ? 'latest_matter.matter_title' : 'm.title';
            $query->whereRaw("LOWER({$matterTitleCol}) LIKE ?", ['%' . strtolower($val) . '%']);
        }
        if ($request->filled('search')) {
            $search = '%' . strtolower($request->input('search')) . '%';
            $query->where(function ($q) use ($search, $refAlias, $matterAlias) {
                $q->whereRaw('LOWER(admins.first_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(admins.last_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(admins.client_id) LIKE ?', [$search])
                    ->orWhereRaw("LOWER({$refAlias}.current_status) LIKE ?", [$search])
                    ->orWhereRaw('LOWER(ws.name) LIKE ?', [$search])
                    ->orWhereRaw("LOWER({$matterAlias}.other_reference) LIKE ?", [$search])
                    ->orWhereRaw("LOWER({$matterAlias}.department_reference) LIKE ?", [$search])
                    ->orWhereRaw("LOWER({$matterAlias}.client_unique_matter_no) LIKE ?", [$search]);
            });
        }
        return $query;
    }

    protected function applySorting($query, Request $request, string $tab, array $config)
    {
        $refAlias = $config['reference_alias'] ?? 'ref';
        
        // First priority: pinned items (is_pinned DESC) - pinned items on top
        // Use CASE to convert boolean to integer for PostgreSQL compatibility
        $query->orderByRaw("CASE WHEN {$refAlias}.is_pinned = true THEN 1 ELSE 0 END DESC");
        
        // Second priority: checklist hold status (only for checklist tab)
        if ($tab === 'checklist') {
            $query->orderByRaw("CASE WHEN COALESCE(latest_matter.checklist_status, 'active') = 'hold' THEN 1 ELSE 0 END ASC");
        }
        
        // Third priority: deadline (ASC) - nearest deadline first, nulls last
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $query->orderByRaw('latest_matter.deadline IS NULL ASC, latest_matter.deadline ASC');
        } else {
            $query->orderByRaw('latest_matter.deadline ASC NULLS LAST');
        }
        
        // Fourth priority: visa expiry (ASC) - closest expiry dates first
        // NULL expiry dates go to the end
        // Use quoted "visaExpiry" for PostgreSQL (case-sensitive column)
        $query->orderByRaw("CASE WHEN admins.\"visaExpiry\" IS NULL OR admins.\"visaExpiry\"::text = '0000-00-00' THEN 1 ELSE 0 END ASC");
        $query->orderByRaw('admins."visaExpiry" ASC');
        
        // Fifth priority: custom staff sort if provided
        $sortField = $request->get('sort');
        $sortDirection = $request->get('direction', 'asc');
        if ($sortField && in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortable = [
                'crm_ref' => 'admins.client_id',
                'name' => 'admins.first_name',
                'dob' => 'admins.dob',
                'stage' => 'ws.name',
            ];
            if (isset($sortable[$sortField])) {
                $query->orderBy($sortable[$sortField], $sortDirection);
            }
        }
        
        // Final tiebreaker: matter ID
        $query->orderBy('latest_matter.matter_id', 'asc');
        
        return $query;
    }

    protected function countActiveFilters(Request $request): int
    {
        $count = 0;
        if ($request->filled('branch')) $count++;
        if ($request->filled('assignee') && $request->input('assignee') !== 'all') $count++;
        if ($request->filled('current_stage')) $count++;
        if ($request->filled('visa_expiry_from')) $count++;
        if ($request->filled('visa_expiry_to')) $count++;
        if ($request->filled('deadline_from')) $count++;
        if ($request->filled('deadline_to')) $count++;
        if ($request->filled('matter_type')) $count++;
        if ($request->filled('search')) $count++;
        return $count;
    }

    protected function calculatePaymentsForMatter($clientId, $matterInternalId): array
    {
        if (!$clientId || !$matterInternalId) {
            return ['total' => '0.00', 'pending' => '0.00'];
        }
        // Payment received = Client Fund Ledger (Deposits only) + Office Receipts
        // Show total for client (sheet displays one row per client with their matter)
        $total = (float) DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->where(function ($q1) {
                    // Client fund: only Deposits (exclude Fee Transfers which have deposit_amount=0)
                    $q1->where('receipt_type', 1)
                        ->where(function ($q2) {
                            $q2->where('client_fund_ledger_type', 'Deposit')
                                ->orWhereNull('client_fund_ledger_type');
                        });
                })->orWhere(function ($q2) {
                    $q2->where('receipt_type', 2)->where('save_type', 'final'); // Office receipts (finalized only)
                });
            })
            ->where(function ($q) {
                $q->whereNull('void_fee_transfer')->orWhere('void_fee_transfer', '!=', 1);
            })
            ->where(function ($q) {
                $q->whereNull('void_invoice')->orWhere('void_invoice', '!=', 1);
            })
            ->sum(DB::raw('COALESCE(deposit_amount, 0)'));
        $pending = (float) DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where('receipt_type', 3)
            ->where(function ($q) {
                $q->whereNull('void_fee_transfer')->orWhere('void_fee_transfer', '!=', 1);
            })
            ->sum(DB::raw('COALESCE(balance_amount, 0)'));
        return ['total' => number_format($total, 2), 'pending' => number_format($pending, 2)];
    }

    /**
     * Toggle pin status for a matter in the sheet.
     */
    public function togglePin(Request $request, string $visaType)
    {
        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet($visaType)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $config = $this->getVisaTypeConfig($visaType);
        if (!$config) {
            return response()->json(['success' => false, 'message' => 'Invalid visa type'], 404);
        }

        $clientId = $request->input('client_id');
        $matterInternalId = $request->input('matter_internal_id');

        if (!$clientId || !$matterInternalId) {
            return response()->json(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        $refTable = $config['reference_table'];
        $refType = $config['reference_type'] ?? $visaType;

        if (!Schema::hasTable($refTable)) {
            return response()->json(['success' => false, 'message' => 'Reference table not found'], 404);
        }

        if ($refTable === 'client_matter_references' && (empty($refType) || $refType === '')) {
            return response()->json(['success' => false, 'message' => 'Visa type config missing reference_type'], 500);
        }

        try {
            $query = DB::table($refTable)
                ->where('client_id', $clientId)
                ->where('client_matter_id', $matterInternalId);
            if (!empty($refType) && $refTable === 'client_matter_references') {
                $query->where('type', $refType);
            }
            $reference = $query->first();

            if ($reference) {
                // Toggle existing pin
                $newPinStatus = !($reference->is_pinned ?? false);
                $updateQuery = DB::table($refTable)
                    ->where('client_id', $clientId)
                    ->where('client_matter_id', $matterInternalId);
                if (!empty($refType) && $refTable === 'client_matter_references') {
                    $updateQuery->where('type', $refType);
                }
                $updateQuery->update([
                        'is_pinned' => $newPinStatus,
                        'updated_by' => Auth::id(),
                        'updated_at' => now(),
                    ]);
            } else {
                // Create new reference record with pin
                $insertData = [
                    'client_id' => $clientId,
                    'client_matter_id' => $matterInternalId,
                    'is_pinned' => true,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if ($refTable === 'client_matter_references') {
                    $insertData['type'] = $refType;
                }
                DB::table($refTable)->insert($insertData);
                $newPinStatus = true;
            }

            return response()->json([
                'success' => true,
                'is_pinned' => $newPinStatus,
                'message' => $newPinStatus ? 'Item pinned to top' : 'Item unpinned'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating pin status: ' . $e->getMessage()], 500);
        }
    }
}
