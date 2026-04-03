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
        // Check if user_logs table exists
        if (Schema::hasTable('user_logs')) {
            // Check if sequence exists, if not create it
            $sequenceExists = DB::selectOne("
                SELECT EXISTS (
                    SELECT 1 
                    FROM pg_sequences 
                    WHERE schemaname = 'public' 
                    AND sequencename = 'user_logs_id_seq'
                ) as exists
            ");
            
            if (!$sequenceExists->exists) {
                // Get the current max id value
                $maxId = DB::table('user_logs')->max('id') ?? 0;
                
                // Create the sequence starting from max + 1
                DB::statement("CREATE SEQUENCE user_logs_id_seq START WITH " . ($maxId + 1));
            }
            
            // Set the default value for id column to use the sequence
            DB::statement("
                ALTER TABLE user_logs 
                ALTER COLUMN id SET DEFAULT nextval('user_logs_id_seq'::regclass)
            ");
            
            // Set the sequence to be owned by the column
            DB::statement("
                ALTER SEQUENCE user_logs_id_seq OWNED BY user_logs.id
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the default value (but keep the sequence)
        if (Schema::hasTable('user_logs')) {
            DB::statement("
                ALTER TABLE user_logs 
                ALTER COLUMN id DROP DEFAULT
            ");
        }
    }
};
