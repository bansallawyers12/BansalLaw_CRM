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
            if (! Schema::hasColumn('staff', 'can_manage_matter_team_access')) {
                $table->boolean('can_manage_matter_team_access')
                    ->default(false)
                    ->after('can_edit_final_invoice');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'can_manage_matter_team_access')) {
                $table->dropColumn('can_manage_matter_team_access');
            }
        });
    }
};
