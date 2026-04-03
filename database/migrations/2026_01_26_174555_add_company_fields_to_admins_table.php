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
     * Adds is_company flag to admins table to identify company leads/clients
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // Check if column already exists before adding
            if (!Schema::hasColumn('admins', 'is_company')) {
                $table->boolean('is_company')->default(false)->nullable()
                    ->comment('Flag to indicate if this is a company lead/client. Company data is stored in companies table.');
            }
        });
        
        // For PostgreSQL, add index for better query performance
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_admins_is_company ON admins(is_company) WHERE is_company = true');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // Drop index first (PostgreSQL)
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS idx_admins_is_company');
            }
            
            // Drop column if it exists
            if (Schema::hasColumn('admins', 'is_company')) {
                $table->dropColumn('is_company');
            }
        });
    }
};
