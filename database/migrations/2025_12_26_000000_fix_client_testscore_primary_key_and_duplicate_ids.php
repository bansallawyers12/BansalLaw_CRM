<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration:
     * 1. Fixes all duplicate IDs by assigning new unique IDs to duplicate records
     * 2. Preserves ALL data - no records are deleted
     * 3. Adds PRIMARY KEY constraint to client_testscore.id column
     * 4. Resets the sequence to the correct value
     * 
     * SAFETY: This migration preserves all data. Only duplicate IDs are updated to new unique values.
     */
    public function up(): void
    {
        if (!Schema::hasTable('client_testscore')) {
            echo "Table 'client_testscore' does not exist. Skipping migration.\n";
            return;
        }

        // Step 1: Check for duplicate IDs
        $duplicateIds = DB::select("
            SELECT id, COUNT(*) as count
            FROM client_testscore
            GROUP BY id
            HAVING COUNT(*) > 1
            ORDER BY id
        ");

        if (count($duplicateIds) > 0) {
            echo "Found " . count($duplicateIds) . " duplicate IDs. Fixing them...\n";
            
            // Get current max ID
            $maxIdResult = DB::selectOne("SELECT COALESCE(MAX(id), 0) as max_id FROM client_testscore");
            $maxId = $maxIdResult->max_id ?? 0;
            $nextId = $maxId + 1;
            
            echo "Current max ID: {$maxId}\n";
            echo "Starting new ID assignment from: {$nextId}\n\n";
            
            // Fix each duplicate ID
            foreach ($duplicateIds as $dup) {
                $duplicateId = $dup->id;
                $count = $dup->count;
                
                echo "Fixing duplicate ID: {$duplicateId} (appears {$count} times)\n";
                
                // Get all records with this duplicate ID, ordered by created_at (keep oldest, update others)
                $records = DB::select("
                    SELECT ctid, id, client_id, test_type, created_at
                    FROM client_testscore
                    WHERE id = ?
                    ORDER BY created_at ASC NULLS LAST, ctid ASC
                ", [$duplicateId]);
                
                // Keep the first record (oldest), update the rest
                for ($i = 1; $i < count($records); $i++) {
                    $newId = $nextId++;
                    $record = $records[$i];
                    
                    echo "  - Updating record (client_id: {$record->client_id}, test_type: {$record->test_type}) to new ID: {$newId}\n";
                    
                    // Update the record with new ID
                    DB::update("
                        UPDATE client_testscore
                        SET id = ?
                        WHERE ctid = ?
                    ", [$newId, $record->ctid]);
                }
            }
            
            echo "\n✅ All duplicate IDs have been fixed.\n";
        } else {
            echo "✅ No duplicate IDs found.\n";
        }

        // Step 2: Verify no duplicates remain
        $remainingDuplicates = DB::select("
            SELECT id, COUNT(*) as count
            FROM client_testscore
            GROUP BY id
            HAVING COUNT(*) > 1
        ");

        if (count($remainingDuplicates) > 0) {
            throw new \Exception("ERROR: Duplicate IDs still exist after fix. Migration aborted.");
        }

        // Step 3: Check if primary key already exists
        $primaryKeyExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM pg_constraint
                WHERE conrelid = (SELECT oid FROM pg_class WHERE relname = 'client_testscore')
                AND contype = 'p'
            ) as exists
        ");

        if ($primaryKeyExists && $primaryKeyExists->exists) {
            echo "Primary key already exists on client_testscore.id\n";
        } else {
            // Step 4: Add PRIMARY KEY constraint
            DB::statement("
                ALTER TABLE client_testscore
                ADD CONSTRAINT client_testscore_pkey PRIMARY KEY (id)
            ");
            echo "✅ Added PRIMARY KEY constraint to client_testscore.id\n";
        }

        // Step 5: Reset sequence to next available ID
        $maxIdResult = DB::selectOne("SELECT COALESCE(MAX(id), 0) as max_id FROM client_testscore");
        $maxId = $maxIdResult->max_id ?? 0;
        $nextId = $maxId + 1;

        $sequenceExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM pg_sequences
                WHERE schemaname = 'public'
                AND sequencename = 'client_testscore_id_seq'
            ) as exists
        ");

        if ($sequenceExists && $sequenceExists->exists) {
            DB::statement("SELECT setval('client_testscore_id_seq', {$nextId}, false)");
            echo "✅ Reset sequence client_testscore_id_seq to {$nextId}\n";
        } else {
            DB::statement("CREATE SEQUENCE client_testscore_id_seq START WITH {$nextId}");
            DB::statement("
                ALTER TABLE client_testscore
                ALTER COLUMN id SET DEFAULT nextval('client_testscore_id_seq'::regclass)
            ");
            DB::statement("
                ALTER SEQUENCE client_testscore_id_seq OWNED BY client_testscore.id
            ");
            echo "✅ Created and configured sequence client_testscore_id_seq starting at {$nextId}\n";
        }

        // Step 6: Final verification
        $finalCount = DB::selectOne("SELECT COUNT(*) as count FROM client_testscore");
        echo "\n✅ Migration completed successfully!\n";
        echo "   Total records preserved: {$finalCount->count}\n";
        echo "   Primary key: ✅ Added\n";
        echo "   Duplicate IDs: ✅ Fixed\n";
        echo "   Sequence: ✅ Reset\n";
    }

    /**
     * Reverse the migrations.
     * 
     * NOTE: We cannot reverse the duplicate ID fixes as we don't know which IDs were original.
     * We can only remove the primary key constraint.
     */
    public function down(): void
    {
        if (!Schema::hasTable('client_testscore')) {
            return;
        }

        try {
            // Remove primary key constraint
            DB::statement("ALTER TABLE client_testscore DROP CONSTRAINT IF EXISTS client_testscore_pkey");
            echo "Removed PRIMARY KEY constraint from client_testscore.id\n";
        } catch (\Exception $e) {
            echo "Could not remove primary key: " . $e->getMessage() . "\n";
        }
    }
};

