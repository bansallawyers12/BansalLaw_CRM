<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_email_logs_sent_created_coalesce ON email_logs (COALESCE(fetch_mail_sent_time, created_at) DESC)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_email_logs_unassigned_lookup ON email_logs (sync_assignment_status, mail_type, client_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_email_logs_manual_uploads ON email_logs (id, client_id, client_matter_id) WHERE client_id > 0 AND client_matter_id > 0 AND (synced_email_id IS NULL OR synced_email_id = 0) AND (imap_uid IS NULL OR imap_uid = 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_email_logs_sent_created_coalesce');
            DB::statement('DROP INDEX IF EXISTS idx_email_logs_unassigned_lookup');
            DB::statement('DROP INDEX IF EXISTS idx_email_logs_manual_uploads');
        }
    }
};
