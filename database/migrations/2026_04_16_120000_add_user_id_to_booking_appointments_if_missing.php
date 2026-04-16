<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * booking_appointments.user_id is used by the CRM and APIs (e.g. mirror of client_id)
 * but was not in the original create migration. Inserts fail when the column is absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_appointments')) {
            return;
        }

        if (Schema::hasColumn('booking_appointments', 'user_id')) {
            return;
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_appointments') || ! Schema::hasColumn('booking_appointments', 'user_id')) {
            return;
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
