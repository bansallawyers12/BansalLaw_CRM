<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL does not support reordering columns in-place.
 * This migration recreates cp_doc_checklists with wf_stage and wf_stage_id
 * placed immediately after client_matter_id, preserving all data.
 *
 * Desired column order:
 *   id, user_id, client_id, client_matter_id,
 *   wf_stage, wf_stage_id,
 *   cp_checklist_name, description, allow_client,
 *   created_at, updated_at
 */
return new class extends Migration
{
    private string $cols = 'id, user_id, client_id, client_matter_id, wf_stage, wf_stage_id, cp_checklist_name, description, allow_client, created_at, updated_at';

    public function up(): void
    {
        DB::statement("
            CREATE TABLE cp_doc_checklists_new (
                id               SERIAL PRIMARY KEY,
                user_id          INTEGER,
                client_id        INTEGER,
                client_matter_id BIGINT,
                wf_stage         VARCHAR(255),
                wf_stage_id      BIGINT,
                cp_checklist_name VARCHAR(255),
                description      TEXT,
                allow_client     INTEGER,
                created_at       TIMESTAMP,
                updated_at       TIMESTAMP
            )
        ");

        DB::statement("INSERT INTO cp_doc_checklists_new ({$this->cols}) SELECT {$this->cols} FROM cp_doc_checklists");

        DB::statement("DROP TABLE cp_doc_checklists");

        DB::statement("ALTER TABLE cp_doc_checklists_new RENAME TO cp_doc_checklists");

        // Rename sequence to a clean canonical name
        DB::statement("ALTER SEQUENCE cp_doc_checklists_new_id_seq RENAME TO cp_doc_checklists_id_seq");

        // Sync sequence to current max id so future inserts don't collide
        DB::statement("SELECT setval('cp_doc_checklists_id_seq', COALESCE((SELECT MAX(id) FROM cp_doc_checklists), 0) + 1, false)");
    }

    public function down(): void
    {
        // Restore original column order (wf_stage / wf_stage_id back at the end)
        $origCols = 'id, user_id, client_id, client_matter_id, cp_checklist_name, description, allow_client, created_at, updated_at, wf_stage, wf_stage_id';

        DB::statement("
            CREATE TABLE cp_doc_checklists_prev (
                id               SERIAL PRIMARY KEY,
                user_id          INTEGER,
                client_id        INTEGER,
                client_matter_id BIGINT,
                cp_checklist_name VARCHAR(255),
                description      TEXT,
                allow_client     INTEGER,
                created_at       TIMESTAMP,
                updated_at       TIMESTAMP,
                wf_stage         VARCHAR(255),
                wf_stage_id      BIGINT
            )
        ");

        DB::statement("INSERT INTO cp_doc_checklists_prev ({$origCols}) SELECT {$origCols} FROM cp_doc_checklists");

        DB::statement("DROP TABLE cp_doc_checklists");

        DB::statement("ALTER TABLE cp_doc_checklists_prev RENAME TO cp_doc_checklists");

        DB::statement("ALTER SEQUENCE cp_doc_checklists_prev_id_seq RENAME TO cp_doc_checklists_id_seq");

        DB::statement("SELECT setval('cp_doc_checklists_id_seq', COALESCE((SELECT MAX(id) FROM cp_doc_checklists), 0) + 1, false)");
    }
};
