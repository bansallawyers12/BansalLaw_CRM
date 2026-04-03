<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds visitor_checklist_status for Visitor Visa Sheet Checklist tab (active, hold, convert_to_client, discontinue).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('client_matters', 'visitor_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->string('visitor_checklist_status', 32)->nullable()
                    ->after('tr_checklist_status')
                    ->comment('Visitor sheet checklist status: active, hold, convert_to_client, discontinue');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('client_matters', 'visitor_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('visitor_checklist_status');
            });
        }
    }
};
