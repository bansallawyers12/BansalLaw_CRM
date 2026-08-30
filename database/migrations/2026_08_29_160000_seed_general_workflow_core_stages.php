<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure General workflow has a full stage path after "New":
     * Checklist → Decision Received → Ready to Close → File Closed.
     * Idempotent: only inserts missing names (case-insensitive).
     */
    public function up(): void
    {
        if (! Schema::hasTable('workflows') || ! Schema::hasTable('workflow_stages')) {
            return;
        }

        $generalId = DB::table('workflows')
            ->whereRaw('LOWER(name) = ?', ['general'])
            ->value('id');

        if (! $generalId) {
            $generalId = DB::table('workflows')->insertGetId([
                'name' => 'General',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $desired = [
            ['name' => 'New', 'sort_order' => 1],
            ['name' => 'Checklist', 'sort_order' => 2],
            ['name' => 'Decision Received', 'sort_order' => 3],
            ['name' => 'Ready to Close', 'sort_order' => 4],
            ['name' => 'File Closed', 'sort_order' => 5],
        ];

        $existing = DB::table('workflow_stages')
            ->where('workflow_id', $generalId)
            ->get(['id', 'name', 'sort_order']);

        $existingByLower = [];
        foreach ($existing as $row) {
            $existingByLower[strtolower(trim((string) $row->name))] = $row;
        }

        foreach ($desired as $stage) {
            $key = strtolower($stage['name']);
            if (isset($existingByLower[$key])) {
                $row = $existingByLower[$key];
                if ((int) ($row->sort_order ?? 0) !== $stage['sort_order']) {
                    DB::table('workflow_stages')
                        ->where('id', $row->id)
                        ->update([
                            'sort_order' => $stage['sort_order'],
                            'updated_at' => now(),
                        ]);
                }
                continue;
            }

            DB::table('workflow_stages')->insert([
                'name' => $stage['name'],
                'workflow_id' => $generalId,
                'sort_order' => $stage['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Remove only the four stages this migration adds (keep "New").
     */
    public function down(): void
    {
        if (! Schema::hasTable('workflows') || ! Schema::hasTable('workflow_stages')) {
            return;
        }

        $generalId = DB::table('workflows')
            ->whereRaw('LOWER(name) = ?', ['general'])
            ->value('id');

        if (! $generalId) {
            return;
        }

        $names = ['Checklist', 'Decision Received', 'Ready to Close', 'File Closed'];
        $ids = DB::table('workflow_stages')
            ->where('workflow_id', $generalId)
            ->where(function ($q) use ($names) {
                foreach ($names as $name) {
                    $q->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)]);
                }
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $fallbackId = DB::table('workflow_stages')
            ->where('workflow_id', $generalId)
            ->whereNotIn('id', $ids)
            ->orderByRaw('COALESCE(sort_order, id) ASC')
            ->value('id');

        if ($fallbackId && Schema::hasTable('client_matters')) {
            DB::table('client_matters')
                ->whereIn('workflow_stage_id', $ids)
                ->update(['workflow_stage_id' => $fallbackId]);
        }

        DB::table('workflow_stages')->whereIn('id', $ids)->delete();
    }
};
