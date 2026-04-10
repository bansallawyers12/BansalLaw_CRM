<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent PostgreSQL ALTERs for legacy notifications tables that still lack
 * columns expected by Notification / ClientsController (e.g. web + CLI pointed at different DBs).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $exists = DB::selectOne(
            "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'notifications' LIMIT 1"
        );
        if (! $exists) {
            return;
        }

        // IF NOT EXISTS is supported in PostgreSQL 9.1+
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS sender_id BIGINT NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS module_id BIGINT NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS url TEXT NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS message TEXT NULL');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS seen SMALLINT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE notifications ADD COLUMN IF NOT EXISTS sender_status SMALLINT NULL DEFAULT 1');

        DB::statement('CREATE INDEX IF NOT EXISTS notifications_sender_id_index ON notifications (sender_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS notifications_module_id_index ON notifications (module_id)');
    }

    public function down(): void
    {
        // Non-reversible: columns may be required by application code.
    }
};
