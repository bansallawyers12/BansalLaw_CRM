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
     * Adds is_for_company field to matters table to filter matters by client type
     */
    public function up(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            // Check if column already exists before adding
            if (!Schema::hasColumn('matters', 'is_for_company')) {
                $table->boolean('is_for_company')->default(false)->nullable()
                    ->comment('If true, this matter is only available for company clients. If false/null, available for personal clients.');
            }
        });
        
        // For PostgreSQL, add index for better query performance
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_matters_is_for_company ON matters(is_for_company) WHERE is_for_company = true');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matters', function (Blueprint $table) {
            // Drop index first (PostgreSQL)
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS idx_matters_is_for_company');
            }
            
            // Drop column if it exists
            if (Schema::hasColumn('matters', 'is_for_company')) {
                $table->dropColumn('is_for_company');
            }
        });
    }
};
