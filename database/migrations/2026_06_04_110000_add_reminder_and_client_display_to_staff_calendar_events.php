<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_calendar_events', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_calendar_events', 'reminder_minutes')) {
                $table->unsignedSmallInteger('reminder_minutes')
                    ->nullable()
                    ->after('notes')
                    ->comment('Minutes before starts_at to fire reminder; null = no reminder');
            }
            if (! Schema::hasColumn('staff_calendar_events', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')
                    ->nullable()
                    ->after('reminder_minutes')
                    ->comment('Set once the reminder has been delivered');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_calendar_events', function (Blueprint $table) {
            $table->dropColumn(['reminder_minutes', 'reminder_sent_at']);
        });
    }
};
