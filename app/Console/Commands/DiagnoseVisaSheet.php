<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostic command to troubleshoot why a visa-type sheet (visitor, tr, student, etc.) shows no data.
 */
class DiagnoseVisaSheet extends Command
{
    protected $signature = 'visa-sheet:diagnose {visa_type=visitor}';

    protected $description = 'Diagnose why a visa-type sheet shows no data';

    public function handle(): int
    {
        $visaType = $this->argument('visa_type');
        $configs = config('sheets.visa_types', []);

        if (!isset($configs[$visaType])) {
            $this->error("Visa type '{$visaType}' not configured. Available: " . implode(', ', array_keys($configs)));
            return 1;
        }

        $config = $configs[$visaType];
        $this->info("Diagnosing: {$config['title']} ({$visaType})");
        $this->newLine();

        // 1. Setup check
        $refTable = $config['reference_table'] ?? '';
        $remindersTable = $config['reminders_table'] ?? '';
        $checklistCol = $config['checklist_status_column'] ?? '';

        $refTableExists = Schema::hasTable($refTable);
        $remindersExists = Schema::hasTable($remindersTable);
        $checklistColExists = Schema::hasColumn('client_matters', $checklistCol);

        $this->line('1. Setup:');
        $this->line("   - {$refTable}: " . ($refTableExists ? 'OK' : 'MISSING'));
        $this->line("   - {$remindersTable}: " . ($remindersExists ? 'OK' : 'MISSING'));
        $this->line("   - client_matters.{$checklistCol}: " . ($checklistColExists ? 'OK' : 'MISSING'));

        if (!$refTableExists || !$remindersExists || !$checklistColExists) {
            $this->warn('   Run: php artisan migrate');
            $this->newLine();
            return 0;
        }
        $this->newLine();

        // 2. Matter matching
        $nickNames = $config['matter_nick_names'] ?? [];
        $patterns = $config['matter_title_patterns'] ?? [];

        $driver = DB::connection()->getDriverName();
        $likeOp = $driver === 'mysql' ? 'LIKE' : 'ILIKE';

        $matterQuery = DB::table('matters')->select('id', 'title', 'nick_name');

        $matterConds = [];
        foreach ($nickNames as $n) {
            $matterConds[] = "LOWER(COALESCE(nick_name, '')) = '" . addslashes(strtolower($n)) . "'";
        }
        foreach ($patterns as $p) {
            $matterConds[] = "LOWER(COALESCE(title, '')) LIKE '%" . addslashes(strtolower($p)) . "%'";
        }

        $cond = null;
        if (empty($matterConds)) {
            $this->warn('2. Matter matching: No nick_names or patterns configured.');
        } else {
            $cond = '(' . implode(' OR ', $matterConds) . ')';
            $matchingMatters = DB::table('matters')
                ->whereRaw($cond)
                ->get(['id', 'title', 'nick_name']);

            $this->line('2. Matters matching config (' . count($matchingMatters) . '):');
            if ($matchingMatters->isEmpty()) {
                $this->warn('   No matters match. Add matter types with nick_name in: ' . implode(', ', $nickNames));
                $this->warn('   Or title containing: ' . implode(', ', $patterns));

                $sampleMatters = DB::table('matters')->take(10)->get(['id', 'title', 'nick_name']);
                $this->line('   Sample matters in DB:');
                foreach ($sampleMatters as $m) {
                    $this->line("     id={$m->id} nick_name='{$m->nick_name}' title='{$m->title}'");
                }
            } else {
                foreach ($matchingMatters->take(20) as $m) {
                    $this->line("     id={$m->id} nick_name='{$m->nick_name}' title='{$m->title}'");
                }
                if ($matchingMatters->count() > 20) {
                    $this->line('     ... and ' . ($matchingMatters->count() - 20) . ' more');
                }
            }
        }
        $this->newLine();

        // 3. Client matters linked to matching matters
        $matterIds = $cond ? DB::table('matters')->whereRaw($cond)->pluck('id') : collect();
        $clientMattersCount = 0;
        if (!empty($matterIds)) {
            $clientMattersCount = DB::table('client_matters')
                ->whereIn('sel_matter_id', $matterIds)
                ->count();
        }

        $this->line('3. Client matters assigned to matching matters: ' . $clientMattersCount);
        if ($clientMattersCount === 0 && !empty($matterIds)) {
            $this->warn('   Assign VISITOR matters to clients via the client matter workflow.');
        }
        $this->newLine();

        // 4. Client filters (type=client/lead, is_archived=0, is_deleted null)
        $clientsCount = DB::table('admins')
            ->whereIn('type', ['client', 'lead'])
            ->where('is_archived', 0)
            ->whereNull('is_deleted')
            ->count();
        $this->line('4. Active clients (type=client/lead, not archived, not deleted): ' . $clientsCount);
        $this->newLine();

        // 5. Ongoing tab: matter_status=1, workflow stage not in lodged/checklist/discontinue
        $ongoingStages = array_map('strtolower', $config['ongoing_stages'] ?? []);
        $lodgedStages = array_map('strtolower', $config['lodged_stages'] ?? []);
        $checklistStages = array_map('strtolower', $config['checklist_early_stages'] ?? []);
        $discontinueStages = array_map('strtolower', $config['discontinue_stages'] ?? []);
        $excluded = array_merge($lodgedStages, $checklistStages, $discontinueStages);

        if (!empty($matterIds)) {
            $ongoingQuery = DB::table('client_matters as cm')
                ->join('matters as m', 'm.id', '=', 'cm.sel_matter_id')
                ->join('admins as a', 'a.id', '=', 'cm.client_id')
                ->leftJoin('workflow_stages as ws', 'ws.id', '=', 'cm.workflow_stage_id')
                ->whereIn('cm.sel_matter_id', $matterIds)
                ->where('cm.matter_status', 1)
                ->whereIn('a.type', ['client', 'lead'])
                ->where('a.is_archived', 0)
                ->whereNull('a.is_deleted');

            if (!empty($excluded)) {
                $ph = implode(',', array_fill(0, count($excluded), '?'));
                $ongoingQuery->whereRaw("(LOWER(TRIM(COALESCE(ws.name, ''))) NOT IN ({$ph}) OR ws.name IS NULL)", $excluded);
            }

            $ongoingCount = $ongoingQuery->count();
            $this->line('5. Records matching Ongoing tab criteria: ' . $ongoingCount);

            if ($ongoingCount === 0 && $clientMattersCount > 0) {
                $stageBreakdown = DB::table('client_matters as cm')
                    ->join('matters as m', 'm.id', '=', 'cm.sel_matter_id')
                    ->join('admins as a', 'a.id', '=', 'cm.client_id')
                    ->leftJoin('workflow_stages as ws', 'ws.id', '=', 'cm.workflow_stage_id')
                    ->whereIn('cm.sel_matter_id', $matterIds)
                    ->whereIn('a.type', ['client', 'lead'])
                    ->where('a.is_archived', 0)
                    ->whereNull('a.is_deleted')
                    ->selectRaw('cm.matter_status, ws.name as stage_name, COUNT(*) as cnt')
                    ->groupBy('cm.matter_status', 'ws.name')
                    ->get();

                $this->warn('   Breakdown by status/stage:');
                foreach ($stageBreakdown as $b) {
                    $this->line("     matter_status={$b->matter_status}, stage='{$b->stage_name}': {$b->cnt}");
                }
            }
        }

        $this->newLine();
        $this->info('Diagnosis complete.');

        return 0;
    }
}
