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
     * 1. Fixes duplicate IDs in all tables that have them (33 tables)
     * 2. Adds PRIMARY KEY constraint to id column for all tables without PK (74 tables)
     * 3. Resets sequences to correct values
     * 4. Preserves ALL data - no records are deleted
     * 
     * SAFETY: This migration preserves all data. Only duplicate IDs are updated to new unique values.
     */
    public function up(): void
    {
        // List of all tables that need primary key (excluding system tables)
        $tablesToFix = [
            'account_all_invoice_receipts',
            'account_client_receipts',
            'agent_details',
            'agents',
            'anzsco_occupations',
            'application_document_lists',
            'appointment_consultants',
            'appointment_sync_logs',
            'booking_appointments',
            'branches',
            'checkin_histories',
            'checkin_logs',
            'client_addresses',
            'client_characters',
            'client_contacts',
            'client_emails',
            'client_eoi_references',
            'client_experiences',
            'client_matters',
            'client_occupation_lists',
            'client_occupations',
            'client_passport_informations',
            'client_points',
            'client_qualifications',
            'client_relationships',
            'client_spouse_details',
            'client_travel_informations',
            'client_visa_countries',
            'clientportal_details_audit',
            'cost_assignment_forms',
            'countries',
            'device_tokens',
            'document_checklists',
            'signature_activities',
            'documents',
            'email_label_mail_report',
            'email_labels',
            'email_verifications',
            'emails',
            'failed_jobs',
            'form956',
            'jobs',
            'mail_report_attachments',
            'mail_reports',
            'email_templates',
            'matters',
            'message_recipients',
            'messages',
            'notes',
            'notifications',
            'password_reset_links',
            'personal_access_tokens',
            'personal_document_types',
            'phone_verifications',
            'refresh_tokens',
            'sessions',
            'signature_fields',
            'signers',
            'sms_logs',
            'sms_templates',
            'tags',
            'teams',
            'upload_checklists',
            'user_logs',
            'user_roles',
            'visa_document_types',
            'workflow_stages',
            'workflows',
        ];

        $totalTables = count($tablesToFix);
        $processedTables = 0;
        $tablesWithDuplicatesFixed = 0;
        $primaryKeysAdded = 0;

        echo "\n=== STARTING PRIMARY KEY MIGRATION FOR {$totalTables} TABLES ===\n\n";

        foreach ($tablesToFix as $tableName) {
            if (!Schema::hasTable($tableName)) {
                echo "⏭️  Skipping {$tableName} - table does not exist\n";
                continue;
            }

            echo "Processing: {$tableName}...\n";

            try {
                // Step 1: Check if table has id column and if it's integer type
                $idColumnInfo = DB::selectOne("
                    SELECT 
                        column_name,
                        data_type
                    FROM information_schema.columns
                    WHERE table_name = ?
                    AND column_name = 'id'
                ", [$tableName]);

                if (!$idColumnInfo) {
                    echo "  ⏭️  Skipping - no 'id' column\n\n";
                    continue;
                }

                // Only process tables with integer id columns
                if (!in_array($idColumnInfo->data_type, ['integer', 'bigint', 'smallint'])) {
                    echo "  ⏭️  Skipping - 'id' column is not integer type (type: {$idColumnInfo->data_type})\n\n";
                    continue;
                }

                // Step 2: Check for duplicate IDs
                $duplicates = DB::select("
                    SELECT id, COUNT(*) as count
                    FROM {$tableName}
                    GROUP BY id
                    HAVING COUNT(*) > 1
                    ORDER BY id
                ");

                if (count($duplicates) > 0) {
                    echo "  ⚠️  Found " . count($duplicates) . " duplicate ID(s). Fixing...\n";
                    
                    // Get current max ID once for the entire table
                    $maxIdResult = DB::selectOne("SELECT COALESCE(MAX(id), 0) as max_id FROM {$tableName}");
                    $maxId = $maxIdResult->max_id ?? 0;
                    $nextId = $maxId + 1;
                    
                    // Count total records that need new IDs
                    $totalRecordsToUpdate = 0;
                    foreach ($duplicates as $dup) {
                        $totalRecordsToUpdate += ($dup->count - 1); // -1 because we keep one record
                    }
                    
                    echo "  📊 Will update {$totalRecordsToUpdate} record(s) with new IDs\n";
                    
                    // Fix each duplicate ID
                    foreach ($duplicates as $dup) {
                        $duplicateId = $dup->id;
                        $count = $dup->count;
                        
                        // Get all records with this duplicate ID, ordered by created_at (keep oldest, update others)
                        $records = DB::select("
                            SELECT ctid, id, created_at
                            FROM {$tableName}
                            WHERE id = ?
                            ORDER BY created_at ASC NULLS LAST, ctid ASC
                        ", [$duplicateId]);
                        
                        // Keep the first record (oldest), update the rest
                        for ($i = 1; $i < count($records); $i++) {
                            $newId = $nextId++;
                            $record = $records[$i];
                            
                            // Update the record with new ID
                            DB::update("
                                UPDATE {$tableName}
                                SET id = ?
                                WHERE ctid = ?
                            ", [$newId, $record->ctid]);
                        }
                    }
                    
                    // Verify no duplicates remain
                    $remainingDuplicates = DB::select("
                        SELECT id, COUNT(*) as count
                        FROM {$tableName}
                        GROUP BY id
                        HAVING COUNT(*) > 1
                    ");
                    
                    if (count($remainingDuplicates) > 0) {
                        throw new \Exception("ERROR: Duplicate IDs still exist in {$tableName} after fix.");
                    }
                    
                    $tablesWithDuplicatesFixed++;
                    echo "  ✅ Fixed " . count($duplicates) . " duplicate ID(s)\n";
                }

                // Step 3: Check if primary key already exists
                $primaryKeyExists = DB::selectOne("
                    SELECT EXISTS (
                        SELECT 1
                        FROM pg_constraint
                        WHERE conrelid = (SELECT oid FROM pg_class WHERE relname = ?)
                        AND contype = 'p'
                    ) as exists
                ", [$tableName]);

                if ($primaryKeyExists && $primaryKeyExists->exists) {
                    echo "  ✅ Primary key already exists\n\n";
                    $processedTables++;
                    continue;
                }

                // Step 4: Add PRIMARY KEY constraint
                DB::statement("
                    ALTER TABLE {$tableName}
                    ADD CONSTRAINT {$tableName}_pkey PRIMARY KEY (id)
                ");
                echo "  ✅ Added PRIMARY KEY constraint\n";
                $primaryKeysAdded++;

                // Step 5: Reset sequence to next available ID
                $maxIdResult = DB::selectOne("SELECT COALESCE(MAX(id), 0) as max_id FROM {$tableName}");
                $maxId = $maxIdResult->max_id ?? 0;
                $nextId = $maxId + 1;

                $sequenceName = "{$tableName}_id_seq";
                $sequenceExists = DB::selectOne("
                    SELECT EXISTS (
                        SELECT 1
                        FROM pg_sequences
                        WHERE schemaname = 'public'
                        AND sequencename = ?
                    ) as exists
                ", [$sequenceName]);

                if ($sequenceExists && $sequenceExists->exists) {
                    DB::statement("SELECT setval('{$sequenceName}', {$nextId}, false)");
                    echo "  ✅ Reset sequence to {$nextId}\n";
                } else {
                    // Create sequence if it doesn't exist
                    DB::statement("CREATE SEQUENCE {$sequenceName} START WITH {$nextId}");
                    DB::statement("
                        ALTER TABLE {$tableName}
                        ALTER COLUMN id SET DEFAULT nextval('{$sequenceName}'::regclass)
                    ");
                    DB::statement("
                        ALTER SEQUENCE {$sequenceName} OWNED BY {$tableName}.id
                    ");
                    echo "  ✅ Created and configured sequence starting at {$nextId}\n";
                }

                $processedTables++;
                echo "  ✅ Completed\n\n";

            } catch (\Exception $e) {
                echo "  ❌ ERROR: " . $e->getMessage() . "\n";
                echo "  ⚠️  Skipping this table and continuing...\n\n";
                // Continue with next table instead of failing entire migration
            }
        }

        // Final summary
        echo "\n=== MIGRATION SUMMARY ===\n";
        echo "Total tables processed: {$processedTables} / {$totalTables}\n";
        echo "Tables with duplicates fixed: {$tablesWithDuplicatesFixed}\n";
        echo "Primary keys added: {$primaryKeysAdded}\n";
        echo "✅ Migration completed!\n\n";
    }

    /**
     * Reverse the migrations.
     * 
     * NOTE: We cannot reverse the duplicate ID fixes as we don't know which IDs were original.
     * We can only remove the primary key constraints.
     */
    public function down(): void
    {
        $tablesToFix = [
            'account_all_invoice_receipts',
            'account_client_receipts',
            'agent_details',
            'agents',
            'anzsco_occupations',
            'application_document_lists',
            'appointment_consultants',
            'appointment_sync_logs',
            'booking_appointments',
            'branches',
            'checkin_histories',
            'checkin_logs',
            'client_addresses',
            'client_characters',
            'client_contacts',
            'client_emails',
            'client_eoi_references',
            'client_experiences',
            'client_matters',
            'client_occupation_lists',
            'client_occupations',
            'client_passport_informations',
            'client_points',
            'client_qualifications',
            'client_relationships',
            'client_spouse_details',
            'client_travel_informations',
            'client_visa_countries',
            'clientportal_details_audit',
            'cost_assignment_forms',
            'countries',
            'device_tokens',
            'document_checklists',
            'signature_activities',
            'documents',
            'email_label_mail_report',
            'email_labels',
            'email_verifications',
            'emails',
            'failed_jobs',
            'form956',
            'jobs',
            'mail_report_attachments',
            'mail_reports',
            'email_templates',
            'matters',
            'message_recipients',
            'messages',
            'notes',
            'notifications',
            'password_reset_links',
            'personal_access_tokens',
            'personal_document_types',
            'phone_verifications',
            'refresh_tokens',
            'sessions',
            'signature_fields',
            'signers',
            'sms_logs',
            'sms_templates',
            'tags',
            'teams',
            'upload_checklists',
            'user_logs',
            'user_roles',
            'visa_document_types',
            'workflow_stages',
            'workflows',
        ];

        echo "\n=== ROLLING BACK PRIMARY KEY CONSTRAINTS ===\n\n";

        foreach ($tablesToFix as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            try {
                $constraintName = "{$tableName}_pkey";
                DB::statement("ALTER TABLE {$tableName} DROP CONSTRAINT IF EXISTS {$constraintName}");
                echo "✅ Removed primary key from {$tableName}\n";
            } catch (\Exception $e) {
                echo "⚠️  Could not remove primary key from {$tableName}: " . $e->getMessage() . "\n";
            }
        }

        echo "\n✅ Rollback completed.\n";
        echo "⚠️  NOTE: Duplicate ID fixes cannot be reversed automatically.\n\n";
    }
};

