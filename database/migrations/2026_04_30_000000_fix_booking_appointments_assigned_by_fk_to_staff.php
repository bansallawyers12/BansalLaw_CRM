<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM auth uses the admin guard with the staff provider, so Auth::id() is staff.id.
     * assigned_by_admin_id was left referencing admins after clients/staff split, causing FK violations.
     * Align the FK with staff (matches BookingAppointment::assignedBy() and CheckInNotificationService).
     */
    public function up(): void
    {
        if (! Schema::hasTable('booking_appointments') || ! Schema::hasColumn('booking_appointments', 'assigned_by_admin_id')) {
            return;
        }

        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->dropForeign(['assigned_by_admin_id']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('
                UPDATE booking_appointments
                SET assigned_by_admin_id = NULL
                WHERE assigned_by_admin_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM staff s WHERE s.id = booking_appointments.assigned_by_admin_id)
            ');
        } else {
            DB::statement('
                UPDATE booking_appointments
                SET assigned_by_admin_id = NULL
                WHERE assigned_by_admin_id IS NOT NULL
                AND assigned_by_admin_id NOT IN (SELECT id FROM staff)
            ');
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_by_admin_id')->nullable()->change();
        });

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->foreign('assigned_by_admin_id')->references('id')->on('staff')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('booking_appointments') || ! Schema::hasColumn('booking_appointments', 'assigned_by_admin_id')) {
            return;
        }

        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->dropForeign(['assigned_by_admin_id']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('
                UPDATE booking_appointments
                SET assigned_by_admin_id = NULL
                WHERE assigned_by_admin_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM admins a WHERE a.id = booking_appointments.assigned_by_admin_id)
            ');
        } else {
            DB::statement('
                UPDATE booking_appointments
                SET assigned_by_admin_id = NULL
                WHERE assigned_by_admin_id IS NOT NULL
                AND assigned_by_admin_id NOT IN (SELECT id FROM admins)
            ');
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->unsignedInteger('assigned_by_admin_id')->nullable()->change();
        });

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->foreign('assigned_by_admin_id')->references('id')->on('admins')->onDelete('set null');
        });
    }
};
