<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_calendar_events')) {
            return;
        }

        if (! Schema::hasColumn('staff_calendar_events', 'status')) {
            Schema::table('staff_calendar_events', function (Blueprint $table) {
                $table->string('status', 32)->default('scheduled')->after('event_type');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_calendar_events')) {
            return;
        }

        if (Schema::hasColumn('staff_calendar_events', 'status')) {
            Schema::table('staff_calendar_events', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }
    }
};
