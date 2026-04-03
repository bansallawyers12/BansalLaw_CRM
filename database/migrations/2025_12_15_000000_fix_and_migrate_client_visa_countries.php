<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if client_visa_countries table exists
        if (!Schema::hasTable('client_visa_countries')) {
            Log::warning('client_visa_countries table does not exist. Skipping migration.');
            return;
        }

        Log::info('Starting client_visa_countries migration...');

        // Step 1: Fix the PostgreSQL sequence first (needed for data migration)
        $this->fixPostgreSQLSequence();

        // Step 2: Migrate data from MySQL source if available
        $this->migrateDataFromMySQL();

        // Step 3: Update sequence after migration (in case new IDs were inserted)
        $this->updateSequenceAfterMigration();
    }

    /**
     * Migrate data from MySQL source database to PostgreSQL
     */
    protected function migrateDataFromMySQL(): void
    {
        $sourceConnection = 'mysql_source';
        $targetConnection = config('database.default');

        // Check if mysql_source connection is configured
        if (!config("database.connections.{$sourceConnection}")) {
            Log::info('MySQL source connection not configured. Skipping data migration.');
            return;
        }

        // Check if we can connect to MySQL source
        try {
            DB::connection($sourceConnection)->getPdo();
            Log::info('Connected to MySQL source database');
        } catch (\Exception $e) {
            Log::info('Cannot connect to MySQL source: ' . $e->getMessage());
            Log::info('Skipping data migration. Only fixing sequence.');
            return;
        }

        // Check if source table exists
        if (!Schema::connection($sourceConnection)->hasTable('client_visa_countries')) {
            Log::info('client_visa_countries table does not exist in MySQL source. Skipping data migration.');
            return;
        }

        // Get data from MySQL source
        $sourceRecords = DB::connection($sourceConnection)
            ->table('client_visa_countries')
            ->get();

        $total = $sourceRecords->count();
        Log::info("Found {$total} records in MySQL source database");

        if ($total === 0) {
            Log::info('No records to migrate from MySQL.');
            return;
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        // Process records in batches
        $batchSize = 100;
        $batch = [];

        foreach ($sourceRecords as $record) {
            try {
                $recordData = (array) $record;
                $recordId = $recordData['id'] ?? null;

                // Sanitize date fields (convert invalid MySQL dates to NULL)
                $recordData = $this->sanitizeDates($recordData);

                // Check if record already exists in PostgreSQL
                $existing = DB::connection($targetConnection)
                    ->table('client_visa_countries')
                    ->where('id', $recordId)
                    ->first();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                // Preserve original ID if it doesn't exist in target
                // Since sequence is already fixed, we can insert with original ID
                if ($recordId && !$this->idExists($targetConnection, 'client_visa_countries', $recordId)) {
                    $recordData['id'] = $recordId;
                } else {
                    // Remove ID to let PostgreSQL auto-generate
                    unset($recordData['id']);
                }

                $batch[] = $recordData;

                // Insert batch when it reaches batch size
                if (count($batch) >= $batchSize) {
                    $result = $this->insertBatch($targetConnection, 'client_visa_countries', $batch);
                    $imported += $result['imported'];
                    $errors += $result['errors'];
                    $batch = [];
                }
            } catch (\Exception $e) {
                $errors++;
                Log::error("Error processing client_visa_countries record ID {$record->id}: " . $e->getMessage());
            }
        }

        // Insert remaining batch
        if (!empty($batch)) {
            $result = $this->insertBatch($targetConnection, 'client_visa_countries', $batch);
            $imported += $result['imported'];
            $errors += $result['errors'];
        }

        Log::info("Data migration complete: {$imported} imported, {$skipped} skipped, {$errors} errors");
    }

    /**
     * Fix PostgreSQL sequence for client_visa_countries.id
     */
    protected function fixPostgreSQLSequence(): void
    {
        Log::info('Fixing PostgreSQL sequence for client_visa_countries.id...');

        // Check if sequence exists, if not create it
        $sequenceExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 
                FROM pg_sequences 
                WHERE schemaname = 'public' 
                AND sequencename = 'client_visa_countries_id_seq'
            ) as exists
        ");

        if (!$sequenceExists->exists) {
            // Get the current max id value
            $maxId = DB::table('client_visa_countries')->max('id') ?? 0;

            // Create the sequence starting from max + 1
            DB::statement("CREATE SEQUENCE client_visa_countries_id_seq START WITH " . ($maxId + 1));
            Log::info("Created sequence starting from " . ($maxId + 1));
        } else {
            // Update sequence to be at least max(id) + 1
            $maxId = DB::table('client_visa_countries')->max('id') ?? 0;
            $currentSeqValue = DB::selectOne("SELECT last_value FROM client_visa_countries_id_seq");
            
            if ($currentSeqValue->last_value <= $maxId) {
                DB::statement("SELECT setval('client_visa_countries_id_seq', " . ($maxId + 1) . ")");
                Log::info("Updated sequence to " . ($maxId + 1));
            }
        }

        // Set the default value for id column to use the sequence
        DB::statement("
            ALTER TABLE client_visa_countries 
            ALTER COLUMN id SET DEFAULT nextval('client_visa_countries_id_seq'::regclass)
        ");

        // Set the sequence to be owned by the column
        DB::statement("
            ALTER SEQUENCE client_visa_countries_id_seq OWNED BY client_visa_countries.id
        ");

        Log::info('PostgreSQL sequence fixed successfully');
    }

    /**
     * Update sequence after migration to ensure it's higher than any existing IDs
     */
    protected function updateSequenceAfterMigration(): void
    {
        $maxId = DB::table('client_visa_countries')->max('id') ?? 0;
        
        if ($maxId > 0) {
            // Get current sequence value
            $currentSeqValue = DB::selectOne("SELECT last_value FROM client_visa_countries_id_seq");
            $newValue = max($currentSeqValue->last_value, $maxId + 1);
            
            // Ensure sequence is at least max(id) + 1
            DB::statement("SELECT setval('client_visa_countries_id_seq', {$newValue}, false)");
            Log::info("Updated sequence to " . $newValue . " (max ID was " . $maxId . ")");
        }
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
        $dateFields = ['visa_expiry_date', 'visa_grant_date', 'created_at', 'updated_at'];
        
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


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the default value (but keep the sequence)
        if (Schema::hasTable('client_visa_countries')) {
            DB::statement("
                ALTER TABLE client_visa_countries 
                ALTER COLUMN id DROP DEFAULT
            ");
        }
    }
};
