<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckMigrationTablesExist extends Command
{
    protected $signature = 'db:check-migration-tables';
    protected $description = 'Check which migration-created tables exist in the default database';

    /** Tables that should exist (from Schema::create migrations) */
    private array $expectedTables = [
        'admins',
        'users',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'client_passport_informations',
        'appointment_consultants',
        'booking_appointments',
        'appointment_sync_logs',
        'device_tokens',
        'refresh_tokens',
        'phone_verifications',
        'email_verifications',
        'message_recipients',
        'anzsco_occupations',
        'sms_logs',
        'sms_templates',
        'signature_activities',
        'email_labels',
        'email_label_email_log',
        'email_log_attachments',
        'clientportal_details_audit',
        'staff_login_logs',
        'companies',
        'appointment_payments',
    ];

    public function handle(): int
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $this->info('Connection: ' . config('database.default'));
        $this->info('Driver: ' . $driver);
        $this->newLine();

        try {
            if ($driver === 'pgsql') {
                $rows = $connection->select(
                    "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name"
                );
                $existingTables = array_map(fn ($r) => $r->table_name, $rows);
            } elseif ($driver === 'mysql') {
                $rows = $connection->select('SHOW TABLES');
                if (empty($rows)) {
                    $existingTables = [];
                } else {
                    $key = array_keys((array) $rows[0])[0];
                    $existingTables = array_map(fn ($r) => ((array) $r)[$key], $rows);
                }
            } else {
                $this->error('Unsupported driver: ' . $driver);
                return 1;
            }
        } catch (\Throwable $e) {
            $this->error('Database error: ' . $e->getMessage());
            return 1;
        }

        sort($existingTables);
        $existingSet = array_flip($existingTables);

        $exist = [];
        $missing = [];
        foreach ($this->expectedTables as $table) {
            if (isset($existingSet[$table])) {
                $exist[] = $table;
            } else {
                $missing[] = $table;
            }
        }

        $this->info('=== Tables that EXIST (' . count($exist) . '/' . count($this->expectedTables) . ') ===');
        foreach ($exist as $t) {
            $this->line('  ✓ ' . $t);
        }

        $this->newLine();
        $this->warn('=== Tables that are MISSING (' . count($missing) . ') ===');
        if (empty($missing)) {
            $this->line('  None – all expected tables exist.');
        } else {
            foreach ($missing as $t) {
                $this->line('  ✗ ' . $t);
            }
        }

        $this->newLine();
        $this->line('Total tables in database: ' . count($existingTables));
        $this->line('Expected from migrations: ' . count($this->expectedTables));

        return 0;
    }
}
