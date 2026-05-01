<?php

use App\Models\Lead;
use App\Services\LeadFollowUpNoteService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill: CRM records still stored as leads but already have at least one active matter
 * with a law matter type (sel_matter_id) should be clients. Aligns with
 * ClientMatter::clientHasActiveAssignedMatter() and LeadMatterAssignedConversion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins') || ! Schema::hasTable('client_matters')) {
            return;
        }

        $leadTypes = Lead::LEAD_TYPE_VALUES;

        $query = DB::table('admins as a')
            ->join('client_matters as cm', 'cm.client_id', '=', 'a.id')
            ->where('cm.matter_status', 1)
            ->whereNotNull('cm.sel_matter_id')
            ->where(function ($q) use ($leadTypes) {
                $q->whereIn('a.type', $leadTypes)
                    ->orWhere('a.type', 1);
            });

        if (Schema::hasColumn('admins', 'is_deleted')) {
            $query->whereNull('a.is_deleted');
        }

        $ids = $query->distinct()->pluck('a.id')->all();

        if ($ids === []) {
            return;
        }

        $now = now();
        $followUp = app(LeadFollowUpNoteService::class);

        $logCols = [];
        if (Schema::hasTable('activities_logs')) {
            $logCols = array_flip(Schema::getColumnListing('activities_logs'));
        }

        $adminsCols = array_flip(Schema::getColumnListing('admins'));

        DB::transaction(function () use ($ids, $followUp, $now, $logCols, $adminsCols, $leadTypes) {
            foreach ($ids as $rawId) {
                $id = (int) $rawId;

                $followUp->completeOpenFollowUpNotes($id);

                $update = [];
                if (isset($adminsCols['type'])) {
                    $update['type'] = 'client';
                }
                if (isset($adminsCols['lead_status'])) {
                    $update['lead_status'] = 'converted';
                }
                if (isset($adminsCols['status'])) {
                    $update['status'] = 1;
                }
                if (isset($adminsCols['updated_at'])) {
                    $update['updated_at'] = $now;
                }

                if ($update === []) {
                    continue;
                }

                $affected = DB::table('admins')
                    ->where('id', $id)
                    ->where(function ($q) use ($leadTypes) {
                        $q->whereIn('type', $leadTypes)
                            ->orWhere('type', 1);
                    })
                    ->update($update);

                if ($affected < 1 || $logCols === []) {
                    continue;
                }

                $activity = [
                    'client_id' => $id,
                    'created_by' => null,
                    'subject' => 'Lead converted to Client',
                    'description' => 'Lead automatically converted to client (migration backfill: active matter with law matter type was already assigned).',
                    'activity_type' => 'lead_converted',
                    'task_status' => 0,
                    'pin' => 0,
                ];
                if (isset($logCols['created_at'])) {
                    $activity['created_at'] = $now;
                }
                if (isset($logCols['updated_at'])) {
                    $activity['updated_at'] = $now;
                }

                $activity = array_intersect_key($activity, $logCols);

                DB::table('activities_logs')->insert($activity);
            }
        });
    }

    public function down(): void
    {
        // Not reversible: prior lead type and activity history are not snapshotted.
    }
};
