<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_conflict_parties')
            || ! Schema::hasColumn('client_conflict_parties', 'opposing_lead_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS ccp_matter_opposing_lead_unique
                 ON client_conflict_parties (client_matter_id, opposing_lead_id)
                 WHERE client_matter_id IS NOT NULL AND opposing_lead_id IS NOT NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS ccp_client_opposing_lead_unique
                 ON client_conflict_parties (client_id, opposing_lead_id)
                 WHERE client_matter_id IS NULL AND opposing_lead_id IS NOT NULL'
            );

            return;
        }

        if ($driver === 'mysql') {
            // MySQL 8+ supports functional unique indexes; guard duplicates at app level on older hosts.
            try {
                DB::statement(
                    'CREATE UNIQUE INDEX ccp_matter_opposing_lead_unique
                     ON client_conflict_parties (client_matter_id, opposing_lead_id)'
                );
            } catch (\Throwable) {
                // Existing duplicates or unsupported — app-level upsert still applies.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_conflict_parties')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS ccp_matter_opposing_lead_unique');
            DB::statement('DROP INDEX IF EXISTS ccp_client_opposing_lead_unique');

            return;
        }

        if ($driver === 'mysql') {
            try {
                DB::statement('DROP INDEX ccp_matter_opposing_lead_unique ON client_conflict_parties');
            } catch (\Throwable) {
                //
            }
        }
    }
};
