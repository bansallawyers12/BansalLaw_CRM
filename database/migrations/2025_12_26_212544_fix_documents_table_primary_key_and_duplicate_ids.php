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
     * 1. Fixes all duplicate IDs by assigning new unique IDs to duplicate records (keeping oldest record)
     * 2. Preserves ALL data - no records are deleted
     * 3. Adds PRIMARY KEY constraint to documents.id column
     * 4. Resets the sequence to the correct value
     * 5. Handles foreign key relationships if any exist
     * 
     * SAFETY: This migration preserves all data. Only duplicate IDs are updated to new unique values.
     * The oldest record for each duplicate ID is kept, others get new incremental IDs.
     */
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            echo "Table 'documents' does not exist. Skipping migration.\n";
            return;
        }

        // Check if id column exists and is integer type
        $idColumnInfo = DB::selectOne("
            SELECT 
                data_type,
                column_default
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = 'documents'
            AND column_name = 'id'
        ");

        if (!$idColumnInfo) {
            echo "Column 'id' does not exist in documents table. Skipping migration.\n";
            return;
        }

        if ($idColumnInfo->data_type !== 'integer' && $idColumnInfo->data_type !== 'bigint') {
            echo "Column 'id' is not an integer type ({$idColumnInfo->data_type}). Skipping migration.\n";
            return;
        }

        // Step 1: Check for duplicate IDs
        $duplicateIds = DB::select("
            SELECT id, COUNT(*) as count
            FROM documents
            GROUP BY id
            HAVING COUNT(*) > 1
            ORDER BY id
        ");

        $totalDuplicates = count($duplicateIds);
        $totalRecordsToFix = 0;
        
        if ($totalDuplicates > 0) {
            // Calculate total records that need new IDs (all but the oldest for each duplicate)
            foreach ($duplicateIds as $dup) {
                $totalRecordsToFix += ($dup->count - 1);
            }
            
            echo "Found {$totalDuplicates} duplicate ID values affecting approximately {$totalRecordsToFix} records.\n";
            echo "Fixing duplicate IDs...\n\n";
            
            // Get current max ID
            $maxIdResult = DB::selectOne("SELECT COALESCE(MAX(id), 0) as max_id FROM documents");
            $maxId = $maxIdResult->max_id ?? 0;
            $nextId = $maxId + 1;
            
            echo "Current max ID: {$maxId}\n";
            echo "Starting new ID assignment from: {$nextId}\n\n";
            
            // Check for foreign key constraints that reference documents.id
            $foreignKeys = DB::select("
                SELECT
                    tc.table_name AS foreign_table_name,
                    kcu.column_name AS foreign_column_name,
                    ccu.table_name AS referenced_table_name,
                    ccu.column_name AS referenced_column_name,
                    tc.constraint_name
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name
                    AND tc.table_schema = kcu.table_schema
                JOIN information_schema.constraint_column_usage AS ccu
                    ON ccu.constraint_name = tc.constraint_name
                    AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                    AND ccu.table_name = 'documents'
                    AND ccu.column_name = 'id'
            ");

            $hasForeignKeys = count($foreignKeys) > 0;
            if ($hasForeignKeys) {
                echo "⚠️  NOTE: Found foreign key constraints referencing documents.id:\n";
                foreach ($foreignKeys as $fk) {
                    echo "   - {$fk->foreign_table_name}.{$fk->foreign_column_name} references documents.id\n";
                }
                echo "   Foreign keys will continue pointing to the oldest record (which keeps the original ID).\n";
                echo "   Other duplicate records will get new IDs.\n\n";
            }
            
            $fixedCount = 0;
            
            // Fix each duplicate ID
            foreach ($duplicateIds as $dup) {
                $duplicateId = $dup->id;
                $count = $dup->count;
                
                echo "Fixing duplicate ID: {$duplicateId} (appears {$count} times)\n";
                
                // Get all records with this duplicate ID, ordered by created_at (keep oldest, update others)
                // Use ctid (PostgreSQL system column) for unique identification
                $records = DB::select("
                    SELECT ctid, id, client_id, file_name, created_at
                    FROM documents
                    WHERE id = ?
                    ORDER BY created_at ASC NULLS LAST, ctid ASC
                ", [$duplicateId]);
                
                // Keep the first record (oldest) with the original ID
                // Update the rest to new IDs
                for ($i = 1; $i < count($records); $i++) {
                    $newId = $nextId++;
                    $record = $records[$i];
                    
                    $fileInfo = $record->file_name ? substr($record->file_name, 0, 30) . '...' : 'N/A';
                    echo "  - Updating record (client_id: {$record->client_id}, file: {$fileInfo}) to new ID: {$newId}\n";
                    
                    // Update the document record with new ID
                    // Note: Foreign keys pointing to the duplicate ID will continue pointing to the oldest record
                    // (which keeps the original ID), so we don't need to update foreign keys
                    DB::update("
                        UPDATE documents
                        SET id = ?
                        WHERE ctid = ?
                    ", [$newId, $record->ctid]);
                    
                    $fixedCount++;
                    
                    // Progress indicator for large batches
                    if ($fixedCount % 50 == 0) {
                        echo "    Progress: {$fixedCount} records fixed...\n";
                    }
                }
            }
            
            echo "\n✅ Fixed {$fixedCount} duplicate records.\n";
        } else {
            echo "✅ No duplicate IDs found.\n";
        }

        // Step 2: Verify no duplicates remain
        $remainingDuplicates = DB::select("
            SELECT id, COUNT(*) as count
            FROM documents
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
                WHERE conrelid = (SELECT oid FROM pg_class WHERE relname = 'documents')
                AND contype = 'p'
            ) as exists
        ");

        if ($primaryKeyExists && $primaryKeyExists->exists) {
            echo "Primary key already exists on documents.id\n";
        } else {
            // Step 4: Add PRIMARY KEY constraint
            DB::statement("
                ALTER TABLE documents
                ADD CONSTRAINT documents_pkey PRIMARY KEY (id)
            ");
            echo "✅ Added PRIMARY KEY constraint to documents.id\n";
        }

        // Step 5: Reset sequence to next available ID
        $maxIdResult = DB::selectOne("SELECT COALESCE(MAX(id), 0) as max_id FROM documents");
        $maxId = $maxIdResult->max_id ?? 0;
        $nextId = $maxId + 1;

        $sequenceExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1
                FROM pg_sequences
                WHERE schemaname = 'public'
                AND sequencename = 'documents_id_seq'
            ) as exists
        ");

        if ($sequenceExists && $sequenceExists->exists) {
            DB::statement("SELECT setval('documents_id_seq', {$nextId}, false)");
            echo "✅ Reset sequence documents_id_seq to {$nextId}\n";
        } else {
            DB::statement("CREATE SEQUENCE documents_id_seq START WITH {$nextId}");
            DB::statement("
                ALTER TABLE documents
                ALTER COLUMN id SET DEFAULT nextval('documents_id_seq'::regclass)
            ");
            DB::statement("
                ALTER SEQUENCE documents_id_seq OWNED BY documents.id
            ");
            echo "✅ Created and configured sequence documents_id_seq starting at {$nextId}\n";
        }

        // Step 6: Final verification
        $finalCount = DB::selectOne("SELECT COUNT(*) as count FROM documents");
        $finalMaxId = DB::selectOne("SELECT COALESCE(MAX(id), 0) as max_id FROM documents");
        
        echo "\n✅ Migration completed successfully!\n";
        echo "   Total records preserved: {$finalCount->count}\n";
        echo "   Maximum ID after fix: {$finalMaxId->max_id}\n";
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
        if (!Schema::hasTable('documents')) {
            return;
        }

        try {
            // Remove primary key constraint
            DB::statement("ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_pkey");
            echo "Removed PRIMARY KEY constraint from documents.id\n";
        } catch (\Exception $e) {
            echo "Could not remove primary key: " . $e->getMessage() . "\n";
        }
    }
};
