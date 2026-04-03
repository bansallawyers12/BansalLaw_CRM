<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds partner_checklist_status for Partner Visa Sheet Checklist tab (active, hold, convert_to_client, discontinue).
     * Covers subclasses: 820, 801, 309, 100, 300.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('client_matters', 'partner_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->string('partner_checklist_status', 32)->nullable()
                    ->after('employer_sponsored_checklist_status')
                    ->comment('Partner Visa sheet checklist status: active, hold, convert_to_client, discontinue');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('client_matters', 'partner_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('partner_checklist_status');
            });
        }
    }
};
