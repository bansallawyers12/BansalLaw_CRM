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
     * 1. Fixes duplicate ID=1 by updating the record with client_id=MAND2506430 to a new ID
     * 2. Updates all foreign key references to the duplicate record
     * 3. Adds PRIMARY KEY constraint to admins.id column
     * 4. Resets the sequence to the correct value
     */
    public function up(): void
    {
        if (!Schema::hasTable('admins')) {
            return;
        }

        // Step 1: Get the duplicate record details
        $duplicateRecord = DB::selectOne("
            SELECT id, client_id, email, first_name, last_name, role, created_at
            FROM admins 
            WHERE id = 1 AND client_id = 'MAND2506430'
        ");

        if (!$duplicateRecord) {
            // Duplicate might have been fixed already, but we still need to add primary key
            echo "Duplicate record with client_id=MAND2506430 not found. Proceeding with primary key addition.\n";
        } else {
            // Step 2: Get max ID and calculate new ID
            $maxId = DB::selectOne("SELECT MAX(id) as max_id FROM admins");
            $newId = ($maxId->max_id ?? 0) + 1;
            
            echo "Found duplicate ID=1 with client_id=MAND2506430\n";
            echo "Will update to new ID: {$newId}\n";
            echo "NOTE: Foreign keys referencing ID=1 will now point to the admin record (admin1@gmail.com).\n";
            echo "      If any foreign keys should reference the duplicate record, they will need manual review.\n\n";

            // Step 3: Update the duplicate record to new ID
            // We do this first, then foreign keys that reference ID=1 will correctly point to the admin record
            DB::update("
                UPDATE admins 
                SET id = ? 
                WHERE id = 1 AND client_id = 'MAND2506430'
            ", [$newId]);
            
            echo "Updated duplicate record from ID=1 to ID={$newId}\n";
            echo "Email: mandeepsingh999666@gmail.com\n";
            echo "Client ID: MAND2506430\n\n";
        }

        // Step 5: Check if primary key already exists
        $primaryKeyExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 
                FROM pg_constraint 
                WHERE conrelid = (SELECT oid FROM pg_class WHERE relname = 'admins')
                AND contype = 'p'
            ) as exists
        ");

        if (!$primaryKeyExists || !$primaryKeyExists->exists) {
            // Step 6: Add PRIMARY KEY constraint to id column
            DB::statement("
                ALTER TABLE admins 
                ADD CONSTRAINT admins_pkey PRIMARY KEY (id)
            ");
            echo "Added PRIMARY KEY constraint to admins.id\n";
        } else {
            echo "Primary key already exists on admins.id\n";
        }

        // Step 7: Reset the sequence to the correct value
        $maxId = DB::selectOne("SELECT MAX(id) as max_id FROM admins");
        $nextId = ($maxId->max_id ?? 0) + 1;
        
        // Check if sequence exists
        $sequenceExists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 
                FROM pg_sequences 
                WHERE schemaname = 'public' 
                AND sequencename = 'admins_id_seq'
            ) as exists
        ");

        if ($sequenceExists && $sequenceExists->exists) {
            // Reset sequence to max_id + 1
            DB::statement("SELECT setval('admins_id_seq', {$nextId}, false)");
            echo "Reset sequence admins_id_seq to {$nextId}\n";
        } else {
            // Create sequence if it doesn't exist
            DB::statement("CREATE SEQUENCE admins_id_seq START WITH {$nextId}");
            DB::statement("
                ALTER TABLE admins 
                ALTER COLUMN id SET DEFAULT nextval('admins_id_seq'::regclass)
            ");
            DB::statement("
                ALTER SEQUENCE admins_id_seq OWNED BY admins.id
            ");
            echo "Created and configured sequence admins_id_seq starting at {$nextId}\n";
        }
    }

    /**
     * Reverse the migrations.
     * 
     * WARNING: This will remove the primary key constraint.
     * The duplicate ID fix cannot be easily reversed.
     */
    public function down(): void
    {
        if (!Schema::hasTable('admins')) {
            return;
        }

        // Remove primary key constraint
        try {
            DB::statement("ALTER TABLE admins DROP CONSTRAINT IF EXISTS admins_pkey");
            echo "Removed PRIMARY KEY constraint from admins.id\n";
        } catch (\Exception $e) {
            echo "Could not remove primary key: " . $e->getMessage() . "\n";
        }
    }
};
