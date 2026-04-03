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
        // Drop the old foreign key constraint (if it exists)
        DB::statement('ALTER TABLE client_contacts DROP CONSTRAINT IF EXISTS client_contacts_verified_by_foreign');
        
        // Clean up orphaned verified_by values (references to admins that no longer exist)
        DB::statement('
            UPDATE client_contacts
            SET verified_by = NULL
            WHERE verified_by IS NOT NULL
            AND verified_by NOT IN (SELECT id FROM admins)
        ');
        
        // Add the correct foreign key constraint pointing to admins table (only if not already present)
        $hasConstraint = DB::selectOne("
            SELECT 1 FROM information_schema.table_constraints
            WHERE table_schema = 'public' AND table_name = 'client_contacts'
            AND constraint_type = 'FOREIGN KEY'
            AND constraint_name LIKE '%verified_by%'
        ");
        if (! $hasConstraint) {
            Schema::table('client_contacts', function (Blueprint $table) {
                $table->foreign('verified_by')
                      ->references('id')
                      ->on('admins')
                      ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new foreign key
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
        });
        
        // Restore the old foreign key (to admins_bkk_24oct2025)
        Schema::table('client_contacts', function (Blueprint $table) {
            $table->foreign('verified_by')
                  ->references('id')
                  ->on('admins_bkk_24oct2025')
                  ->onDelete('set null');
        });
    }
};
