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
            $constraints = DB::select(
                "SELECT tc.constraint_name
                 FROM information_schema.table_constraints tc
                 INNER JOIN information_schema.key_column_usage kcu
                     ON tc.constraint_name = kcu.constraint_name
                     AND tc.table_schema = kcu.table_schema
                 WHERE tc.table_schema = 'public'
                   AND tc.table_name = 'client_occupations'
                   AND tc.constraint_type = 'FOREIGN KEY'
                   AND kcu.column_name = 'anzsco_occupation_id'"
            );

            foreach ($constraints as $constraint) {
                $name = str_replace('"', '""', $constraint->constraint_name);
                DB::statement("ALTER TABLE client_occupations DROP CONSTRAINT IF EXISTS \"{$name}\"");
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

