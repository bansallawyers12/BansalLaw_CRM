<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds tr_checklist_status for TR Sheet Checklist tab (active, hold, convert_to_client, discontinue).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('client_matters', 'tr_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->string('tr_checklist_status', 32)->nullable()
                    ->after('workflow_stage_id')
                    ->comment('TR sheet checklist status: active, hold, convert_to_client, discontinue');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('client_matters', 'tr_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('tr_checklist_status');
            });
        }
    }
};
