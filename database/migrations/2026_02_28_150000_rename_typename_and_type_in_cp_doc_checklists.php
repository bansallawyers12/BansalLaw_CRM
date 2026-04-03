<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames two columns in cp_doc_checklists:
 *   typename  (VARCHAR 255, stores stage name text) → wf_stage
 *   type      (VARCHAR 100, stored old stage slug)  → wf_stage_id (INTEGER, FK to workflow_stages.id)
 *
 * No data is lost:
 *   - All existing typename values are copied to wf_stage.
 *   - wf_stage_id is populated by matching typename against workflow_stages.name.
 *     Rows with no matching stage get NULL (nullable FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Add new columns ─────────────────────────────────────────────
        Schema::table('cp_doc_checklists', function (Blueprint $table) {
            $table->string('wf_stage', 255)->nullable()->after('typename');
            $table->unsignedBigInteger('wf_stage_id')->nullable()->after('wf_stage');
        });

        // ── 2. Copy existing typename text → wf_stage ─────────────────────
        DB::statement('UPDATE cp_doc_checklists SET wf_stage = typename');

        // ── 3. Populate wf_stage_id by matching stage name ─────────────────
        DB::statement('
            UPDATE cp_doc_checklists c
            SET wf_stage_id = ws.id
            FROM workflow_stages ws
            WHERE ws.name = c.typename
        ');

        // ── 4. Drop old columns ────────────────────────────────────────────
        Schema::table('cp_doc_checklists', function (Blueprint $table) {
            $table->dropColumn(['typename', 'type']);
        });
    }

    public function down(): void
    {
        // ── 1. Re-add old columns ──────────────────────────────────────────
        Schema::table('cp_doc_checklists', function (Blueprint $table) {
            $table->string('typename', 255)->nullable()->after('client_matter_id');
            $table->string('type', 100)->nullable()->after('typename');
        });

        // ── 2. Restore typename from wf_stage ─────────────────────────────
        DB::statement('UPDATE cp_doc_checklists SET typename = wf_stage');

        // ── 3. Drop new columns ────────────────────────────────────────────
        Schema::table('cp_doc_checklists', function (Blueprint $table) {
            $table->dropColumn(['wf_stage', 'wf_stage_id']);
        });
    }
};
