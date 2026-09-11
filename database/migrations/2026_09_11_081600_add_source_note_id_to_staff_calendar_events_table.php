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

        if (! Schema::hasColumn('staff_calendar_events', 'source_note_id')) {
            Schema::table('staff_calendar_events', function (Blueprint $table) {
                $table->unsignedBigInteger('source_note_id')->nullable()->after('created_by_staff_id');
                $table->unique('source_note_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_calendar_events')) {
            return;
        }

        if (Schema::hasColumn('staff_calendar_events', 'source_note_id')) {
            Schema::table('staff_calendar_events', function (Blueprint $table) {
                $table->dropUnique(['source_note_id']);
                $table->dropColumn('source_note_id');
            });
        }
    }
};
