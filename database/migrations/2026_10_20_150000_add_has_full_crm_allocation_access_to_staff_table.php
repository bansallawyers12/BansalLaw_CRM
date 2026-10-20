<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'has_full_crm_allocation_access')) {
                $table->boolean('has_full_crm_allocation_access')
                    ->default(false)
                    ->after('can_manage_matter_team_access')
                    ->comment('Super Admin grant: all clients, leads, matters, and synced mail');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff') || ! Schema::hasColumn('staff', 'has_full_crm_allocation_access')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('has_full_crm_allocation_access');
        });
    }
};
