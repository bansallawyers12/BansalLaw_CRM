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
     * This migration fixes the PostgreSQL sequence for activities_logs.id
     * to prevent "duplicate key value violates unique constraint" errors.
     * 
     * IMPORTANT: This migration does NOT delete or modify any existing records.
     * It only resets the sequence counter to ensure future inserts get correct IDs.
     */
    public function up(): void
    {
        // Check if activities_logs table exists
        if (Schema::hasTable('activities_logs')) {
            // Get the current max id value from the table
            $maxId = DB::table('activities_logs')->max('id') ?? 0;
            
            // Check if sequence exists
            $sequenceExists = DB::selectOne("
                SELECT EXISTS (
                    SELECT 1 
                    FROM pg_sequences 
                    WHERE schemaname = 'public' 
                    AND sequencename = 'activities_logs_id_seq'
                ) as exists
            ");
            
            if ($sequenceExists && $sequenceExists->exists) {
                // Sequence exists - reset it to max_id + 1
                // This ensures the next insert will use an ID that doesn't exist yet
                DB::statement("
                    SELECT setval('activities_logs_id_seq', " . ($maxId + 1) . ", false)
                ");
            } else {
                // Sequence doesn't exist - create it starting from max + 1
                DB::statement("CREATE SEQUENCE activities_logs_id_seq START WITH " . ($maxId + 1));
                
                // Set the default value for id column to use the sequence
                DB::statement("
                    ALTER TABLE activities_logs 
                    ALTER COLUMN id SET DEFAULT nextval('activities_logs_id_seq'::regclass)
                ");
                
                // Set the sequence to be owned by the column
                DB::statement("
                    ALTER SEQUENCE activities_logs_id_seq OWNED BY activities_logs.id
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: We don't drop the sequence in down() to avoid breaking
     * the table. The sequence will remain but may need to be reset again
     * if data is restored from a backup.
     */
    public function down(): void
    {
        // We don't reverse the sequence fix as it's a data integrity fix
        // Removing it could cause the same issue to reoccur
    }
};

