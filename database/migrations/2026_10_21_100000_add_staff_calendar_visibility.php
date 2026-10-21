<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff') && ! Schema::hasColumn('staff', 'staff_calendar_visibility')) {
            Schema::table('staff', function (Blueprint $table) {
                // own = default (my calendar only); all = every staff + website calendars; assigned = grants table only
                $table->string('staff_calendar_visibility', 20)->default('own')->after('default_calendar_type');
            });
        }

        if (! Schema::hasTable('staff_calendar_view_grants')) {
            Schema::create('staff_calendar_view_grants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id');
                $table->unsignedBigInteger('visible_staff_id')->nullable();
                $table->string('booking_calendar_type', 20)->nullable();
                $table->timestampsTz();

                $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
                $table->foreign('visible_staff_id')->references('id')->on('staff')->onDelete('cascade');
                $table->unique(['staff_id', 'visible_staff_id'], 'uniq_scvg_staff_visible_staff');
                $table->unique(['staff_id', 'booking_calendar_type'], 'uniq_scvg_staff_booking_type');
                $table->index('staff_id', 'idx_scvg_staff_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_calendar_view_grants');

        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'staff_calendar_visibility')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('staff_calendar_visibility');
            });
        }
    }
};
