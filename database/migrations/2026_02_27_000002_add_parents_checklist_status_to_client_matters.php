<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds parents_checklist_status for Parents Visa Sheet Checklist tab (active, hold, convert_to_client, discontinue).
     * Covers subclasses: 103, 143, 173, 864, 884, 870.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('client_matters', 'parents_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->string('parents_checklist_status', 32)->nullable()
                    ->after('partner_checklist_status')
                    ->comment('Parents Visa sheet checklist status: active, hold, convert_to_client, discontinue');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('client_matters', 'parents_checklist_status')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('parents_checklist_status');
            });
        }
    }
};
