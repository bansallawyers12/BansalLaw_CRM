<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_appointments')) {
            return;
        }
        if (Schema::hasColumn('booking_appointments', 'website_status_code')) {
            return;
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->unsignedTinyInteger('website_status_code')
                ->nullable()
                ->after('status')
                ->comment('Public booking UI status 0–11 (optional)');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_appointments') || ! Schema::hasColumn('booking_appointments', 'website_status_code')) {
            return;
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->dropColumn('website_status_code');
        });
    }
};
