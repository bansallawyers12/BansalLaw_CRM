<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if migrations table exists
        if (Schema::hasTable('migrations')) {
            // Check if sequence exists, if not create it
            $sequenceExists = DB::selectOne("
                SELECT EXISTS (
                    SELECT 1 
                    FROM pg_sequences 
                    WHERE schemaname = 'public' 
                    AND sequencename = 'migrations_id_seq'
                ) as exists
            ");
            
            if (!$sequenceExists->exists) {
                // Get the current max id value
                $maxId = DB::table('migrations')->max('id') ?? 0;
                
                // Create the sequence starting from max + 1
                DB::statement("CREATE SEQUENCE migrations_id_seq START WITH " . ($maxId + 1));
            }
            
            // Set the default value for id column to use the sequence
            DB::statement("
                ALTER TABLE migrations 
                ALTER COLUMN id SET DEFAULT nextval('migrations_id_seq'::regclass)
            ");
            
            // Set the sequence to be owned by the column
            DB::statement("
                ALTER SEQUENCE migrations_id_seq OWNED BY migrations.id
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the default value (but keep the sequence)
        if (Schema::hasTable('migrations')) {
            DB::statement("
                ALTER TABLE migrations 
                ALTER COLUMN id DROP DEFAULT
            ");
        }
    }
};
