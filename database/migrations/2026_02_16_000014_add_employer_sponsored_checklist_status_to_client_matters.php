<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds employer_sponsored_checklist_status for Employer Sponsored Visa Sheet Checklist tab (active, hold, convert_to_client, discontinue).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('client_matters', 'employer_sponsored_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->string('employer_sponsored_checklist_status', 32)->nullable()
                    ->after('pr_checklist_status')
                    ->comment('Employer Sponsored sheet checklist status: active, hold, convert_to_client, discontinue');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('client_matters', 'employer_sponsored_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('employer_sponsored_checklist_status');
            });
        }
    }
};
