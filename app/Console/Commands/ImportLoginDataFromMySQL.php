<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ImportLoginDataFromMySQL extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:login-data 
                            {--source=mysql_source : MySQL source connection name}
                            {--target= : Target connection name (default: default connection)}
                            {--table=* : Specific tables to import (only admins or staff_login_logs allowed)}
                            {--skip-existing : Skip records that already exist}
                            {--update-existing : Update existing records instead of skipping}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import login-related data (admins, staff_login_logs) from MySQL to PostgreSQL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sourceConnection = $this->option('source');
        $targetConnection = $this->option('target') ?: config('database.default');
        $requestedTables = $this->option('table');
        $skipExisting = $this->option('skip-existing');
        $updateExisting = $this->option('update-existing');

        // Define allowed tables (only login-related tables)
        $allowedTables = ['admins', 'staff_login_logs'];
        
        // If no tables specified, import all allowed tables
        if (empty($requestedTables)) {
            $tablesToImport = $allowedTables;
        } else {
            // Validate that only allowed tables are specified
            $invalidTables = array_diff($requestedTables, $allowedTables);
            if (!empty($invalidTables)) {
                $this->error("✗ Invalid table(s) specified: " . implode(', ', $invalidTables));
                $this->info("Only the following tables are allowed: " . implode(', ', $allowedTables));
                return 1;
            }
            $tablesToImport = $requestedTables;
        }

        $this->info("Starting import from MySQL ({$sourceConnection}) to PostgreSQL ({$targetConnection})...");
        $this->newLine();

        // Check source connection
        try {
            DB::connection($sourceConnection)->getPdo();
            $this->info("✓ Connected to MySQL source database");
        } catch (\Exception $e) {
            $this->error("✗ Cannot connect to MySQL source: " . $e->getMessage());
            $this->info("Please check your MYSQL_SOURCE_* environment variables in .env file");
            return 1;
        }

        // Check target connection
        try {
            DB::connection($targetConnection)->getPdo();
            $this->info("✓ Connected to PostgreSQL target database");
        } catch (\Exception $e) {
            $this->error("✗ Cannot connect to PostgreSQL target: " . $e->getMessage());
            return 1;
        }

        $this->newLine();

        $totalImported = 0;
        $totalSkipped = 0;
        $totalUpdated = 0;
        $totalErrors = 0;

        // Import admins table
        if (in_array('admins', $tablesToImport)) {
            $result = $this->importAdmins($sourceConnection, $targetConnection, $skipExisting, $updateExisting);
            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];
            $totalUpdated += $result['updated'];
            $totalErrors += $result['errors'];
        }

        // Import staff_login_logs table (formerly user_logs)
        if (in_array('staff_login_logs', $tablesToImport)) {
            $result = $this->importStaffLoginLogs($sourceConnection, $targetConnection, $skipExisting, $updateExisting);
            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];
            $totalUpdated += $result['updated'];
            $totalErrors += $result['errors'];
        }

        $this->newLine();
        $this->info("=== Import Summary ===");
        $this->info("Total Imported: {$totalImported}");
        $this->info("Total Updated: {$totalUpdated}");
        $this->info("Total Skipped: {$totalSkipped}");
        $this->info("Total Errors: {$totalErrors}");

        return 0;
    }

    /**
     * Import admins table
     */
    protected function importAdmins($sourceConnection, $targetConnection, $skipExisting, $updateExisting)
    {
        $this->info("Importing 'admins' table...");

        if (!Schema::connection($sourceConnection)->hasTable('admins')) {
            $this->warn("  ⚠ Table 'admins' does not exist in source database");
            return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => 0];
        }

        // Ensure target table exists
        if (!Schema::connection($targetConnection)->hasTable('admins')) {
            $this->error("  ✗ Table 'admins' does not exist in target database. Please run migrations first.");
            return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => 1];
        }

        $sourceAdmins = DB::connection($sourceConnection)->table('admins')->get();
        $total = $sourceAdmins->count();
        
        $this->info("  Found {$total} admin records in MySQL");

        if ($total === 0) {
            return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => 0];
        }

        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Process in batches to avoid transaction issues
        $batchSize = 100;
        $batch = [];

            foreach ($sourceAdmins as $admin) {
                try {
                    // Get data as array, but ensure password is included
                    $adminData = (array) $admin;
                    // Password might be hidden, so get it directly from the object
                    if (empty($adminData['password']) && isset($admin->password)) {
                        $adminData['password'] = $admin->password;
                    }
                    
                    // Sanitize date fields (convert invalid MySQL dates to NULL)
                    $adminData = $this->sanitizeDates($adminData);
                
                // Remove id to let PostgreSQL auto-generate, or use existing if updating
                $adminId = $adminData['id'] ?? null;
                unset($adminData['id']);

                // Check if admin exists (by email, which is unique)
                $existing = DB::connection($targetConnection)
                    ->table('admins')
                    ->where('email', $adminData['email'])
                    ->first();

                if ($existing) {
                    if ($skipExisting) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    if ($updateExisting) {
                        // Update existing record in individual transaction
                        DB::connection($targetConnection)->beginTransaction();
                        try {
                            unset($adminData['created_at']); // Preserve original created_at
                            // Only update password if source has one and target doesn't, or if explicitly updating
                            if (!empty($adminData['password']) || empty($existing->password)) {
                                // Update with password
                            } else {
                                // Don't overwrite existing password if source password is empty
                                unset($adminData['password']);
                            }
                            DB::connection($targetConnection)
                                ->table('admins')
                                ->where('id', $existing->id)
                                ->update($adminData);
                            DB::connection($targetConnection)->commit();
                            $updated++;
                        } catch (\Exception $e) {
                            DB::connection($targetConnection)->rollBack();
                            $errors++;
                            Log::error("Error updating admin ID {$admin->id}: " . $e->getMessage());
                        }
                    } else {
                        $skipped++;
                    }
                } else {
                    // Add to batch for insert
                    if ($adminId && !$this->idExists($targetConnection, 'admins', $adminId)) {
                        $adminData['id'] = $adminId;
                    }
                    $batch[] = $adminData;
                }

                // Insert batch when it reaches batch size
                if (count($batch) >= $batchSize) {
                    $result = $this->insertBatch($targetConnection, 'admins', $batch);
                    $imported += $result['imported'];
                    $errors += $result['errors'];
                    $batch = [];
                }

                $bar->advance();
            } catch (\Exception $e) {
                $errors++;
                Log::error("Error processing admin ID {$admin->id}: " . $e->getMessage());
                $bar->advance();
            }
        }

        // Insert remaining batch
        if (!empty($batch)) {
            $result = $this->insertBatch($targetConnection, 'admins', $batch);
            $imported += $result['imported'];
            $errors += $result['errors'];
        }

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Admins: {$imported} imported, {$updated} updated, {$skipped} skipped, {$errors} errors");

        return ['imported' => $imported, 'skipped' => $skipped, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Import staff_login_logs table (formerly user_logs).
     * Reads from staff_login_logs or user_logs in source (for legacy MySQL compatibility).
     */
    protected function importStaffLoginLogs($sourceConnection, $targetConnection, $skipExisting, $updateExisting)
    {
        $sourceTable = Schema::connection($sourceConnection)->hasTable('staff_login_logs')
            ? 'staff_login_logs'
            : (Schema::connection($sourceConnection)->hasTable('user_logs') ? 'user_logs' : null);

        if (!$sourceTable) {
            $this->warn("  ⚠ Table 'staff_login_logs' or 'user_logs' does not exist in source database");
            return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => 0];
        }

        $this->info("Importing '{$sourceTable}' from source to 'staff_login_logs'...");

        // Ensure target table exists
        if (!Schema::connection($targetConnection)->hasTable('staff_login_logs')) {
            $this->error("  ✗ Table 'staff_login_logs' does not exist in target database. Please run migrations first.");
            return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => 1];
        }

        $sourceLogs = DB::connection($sourceConnection)->table($sourceTable)->get();
        $total = $sourceLogs->count();
        
        $this->info("  Found {$total} log records in MySQL");

        if ($total === 0) {
            return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => 0];
        }

        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::connection($targetConnection)->beginTransaction();

        try {
            foreach ($sourceLogs as $log) {
                try {
                    $logData = (array) $log;
                    $logId = $logData['id'] ?? null;
                    unset($logData['id']);

                    // For logs, we typically just insert (they're historical records)
                    // But check if we want to skip duplicates
                    if ($skipExisting && $logId) {
                        $existing = DB::connection($targetConnection)
                            ->table('staff_login_logs')
                            ->where('id', $logId)
                            ->first();
                        
                        if ($existing) {
                            $skipped++;
                            $bar->advance();
                            continue;
                        }
                    }

                    // Insert new log record
                    // Let PostgreSQL auto-generate ID, or use original if preserving
                    if ($logId && !$this->idExists($targetConnection, 'staff_login_logs', $logId)) {
                        $logData['id'] = $logId;
                    }

                    DB::connection($targetConnection)->table('staff_login_logs')->insert($logData);
                    $imported++;

                    $bar->advance();
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("Error importing log ID {$log->id}: " . $e->getMessage());
                    $bar->advance();
                }
            }

            DB::connection($targetConnection)->commit();
        } catch (\Exception $e) {
            DB::connection($targetConnection)->rollBack();
            $this->error("\n  ✗ Transaction failed: " . $e->getMessage());
            $errors++;
        }

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Staff Login Logs: {$imported} imported, {$updated} updated, {$skipped} skipped, {$errors} errors");

        return ['imported' => $imported, 'skipped' => $skipped, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Check if an ID already exists in the target table
     */
    protected function idExists($connection, $table, $id)
    {
        return DB::connection($connection)
            ->table($table)
            ->where('id', $id)
            ->exists();
    }

    /**
     * Sanitize date fields - convert invalid MySQL dates (0000-00-00) to NULL
     */
    protected function sanitizeDates($data)
    {
        $dateFields = ['naati_date', 'py_date', 'visa_expiry_verified_at', 'cp_token_generated_at', 'token_generated_at', 'created_at', 'updated_at'];
        
        foreach ($dateFields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                // Check for invalid MySQL dates
                if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00' || 
                    (is_string($value) && strpos($value, '0000-00-00') !== false)) {
                    $data[$field] = null;
                } elseif ($value === '' || $value === null) {
                    $data[$field] = null;
                }
            }
        }
        
        return $data;
    }

    /**
     * Insert a batch of records with individual transaction per record
     */
    protected function insertBatch($connection, $table, $batch)
    {
        $imported = 0;
        $errors = 0;

        foreach ($batch as $record) {
            DB::connection($connection)->beginTransaction();
            try {
                DB::connection($connection)->table($table)->insert($record);
                DB::connection($connection)->commit();
                $imported++;
            } catch (\Exception $e) {
                DB::connection($connection)->rollBack();
                $errors++;
                $recordId = $record['id'] ?? 'unknown';
                Log::error("Error inserting into {$table} (ID: {$recordId}): " . $e->getMessage());
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }
}
