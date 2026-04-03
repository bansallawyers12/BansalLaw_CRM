<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops follow_up_required and follow_up_date from booking_appointments.
     * These columns had no UI and were never used by staff.
     *
     * @see docs/BOOKING_APPOINTMENTS_TABLE_COLUMNS.md – Column removal guide
     */
    public function up(): void
    {
        Schema::table('booking_appointments', function (Blueprint $table) {
            if (Schema::hasColumn('booking_appointments', 'follow_up_required')) {
                $table->dropColumn('follow_up_required');
            }
            if (Schema::hasColumn('booking_appointments', 'follow_up_date')) {
                $table->dropColumn('follow_up_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->boolean('follow_up_required')->default(false)->after('admin_notes');
            $table->date('follow_up_date')->nullable()->after('follow_up_required');
        });
    }
};
