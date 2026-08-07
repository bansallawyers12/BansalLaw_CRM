<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zoho_calendar_staff_maps')) {
            return;
        }

        Schema::table('zoho_calendar_staff_maps', function (Blueprint $table) {
            if (! Schema::hasColumn('zoho_calendar_staff_maps', 'display_name')) {
                $table->string('display_name', 150)->nullable()->after('zoho_email');
            }
            if (! Schema::hasColumn('zoho_calendar_staff_maps', 'is_org_default')) {
                $table->boolean('is_org_default')->default(false)->after('sync_enabled');
            }
            if (! Schema::hasColumn('zoho_calendar_staff_maps', 'notes')) {
                $table->string('notes', 500)->nullable()->after('last_error');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('zoho_calendar_staff_maps')) {
            return;
        }

        Schema::table('zoho_calendar_staff_maps', function (Blueprint $table) {
            if (Schema::hasColumn('zoho_calendar_staff_maps', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('zoho_calendar_staff_maps', 'is_org_default')) {
                $table->dropColumn('is_org_default');
            }
            if (Schema::hasColumn('zoho_calendar_staff_maps', 'display_name')) {
                $table->dropColumn('display_name');
            }
        });
    }
};
