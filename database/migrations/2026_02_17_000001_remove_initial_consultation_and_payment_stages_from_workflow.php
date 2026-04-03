<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove "Initial Consultation" and "Initial Payment and Documents Received"
     * from workflow_stages. Reassign affected client_matters and applications to
     * the first remaining stage (by sort_order).
     */
    public function up(): void
    {
        // Get stage IDs to remove (match common name variants)
        $idsToRemove = DB::table('workflow_stages')
            ->where(function ($q) {
                $q->whereRaw("LOWER(TRIM(name)) = 'initial consultation'")
                  ->orWhereRaw("LOWER(TRIM(name)) = 'initial payment and documents received'");
            })
            ->pluck('id')
            ->toArray();

        if (empty($idsToRemove)) {
            return;
        }

        // Get first remaining stage (lowest sort_order)
        $firstStage = DB::table('workflow_stages')
            ->whereNotIn('id', $idsToRemove)
            ->orderByRaw('COALESCE(sort_order, id) ASC')
            ->first();

        if (!$firstStage) {
            return; // Cannot remove all stages
        }

        $newStageId = $firstStage->id;
        $newStageName = $firstStage->name;

        // Update client_matters that point to removed stages
        DB::table('client_matters')
            ->whereIn('workflow_stage_id', $idsToRemove)
            ->update(['workflow_stage_id' => $newStageId]);

        // Update applications.stage where it matches removed stage names (if applications table exists)
        if (\Illuminate\Support\Facades\Schema::hasTable('applications')) {
            $removedNames = DB::table('workflow_stages')
                ->whereIn('id', $idsToRemove)
                ->pluck('name')
                ->toArray();

            DB::table('applications')
                ->whereIn('stage', $removedNames)
                ->update(['stage' => $newStageName]);
        }

        // Delete the workflow stages
        DB::table('workflow_stages')->whereIn('id', $idsToRemove)->delete();
    }

    /**
     * Reverse: we cannot restore deleted stages. Down is a no-op.
     */
    public function down(): void
    {
        // Cannot restore deleted workflow stages without their original data
    }
};
