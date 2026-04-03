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
        // Drop the old foreign key constraint if it exists
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL: Get constraint name from pg_constraint
            $constraints = DB::select(
                "SELECT conname 
                 FROM pg_constraint 
                 WHERE conrelid = 'client_occupations'::regclass 
                 AND contype = 'f' 
                 AND conkey::text LIKE '%anzsco_occupation_id%'"
            );
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE client_occupations DROP CONSTRAINT IF EXISTS {$constraint->conname}");
            }
        } else {
            // MySQL: Get the actual foreign key constraint name from the database
            $foreignKeys = DB::select(
                "SELECT CONSTRAINT_NAME 
                 FROM information_schema.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'client_occupations' 
                 AND COLUMN_NAME = 'anzsco_occupation_id' 
                 AND REFERENCED_TABLE_NAME IS NOT NULL"
            );

            if (!empty($foreignKeys)) {
                foreach ($foreignKeys as $fk) {
                    $constraintName = $fk->CONSTRAINT_NAME;
                    DB::statement("ALTER TABLE client_occupations DROP FOREIGN KEY `{$constraintName}`");
                }
            }
        }

        // Recreate the correct foreign key constraint
        Schema::table('client_occupations', function (Blueprint $table) {
            // Make sure the column exists (it should, but just in case)
            if (!Schema::hasColumn('client_occupations', 'anzsco_occupation_id')) {
                $table->unsignedBigInteger('anzsco_occupation_id')->nullable();
            }

            // Add the correct foreign key constraint pointing to 'anzsco_occupations' table
            $table->foreign('anzsco_occupation_id')
                  ->references('id')
                  ->on('anzsco_occupations')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_occupations', function (Blueprint $table) {
            $table->dropForeign(['anzsco_occupation_id']);
        });
    }
};

