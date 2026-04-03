<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->dropForeign('booking_appointments_client_id_foreign');
        });

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->foreign('client_id')
                ->references('id')
                ->on('admins')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->dropForeign('booking_appointments_client_id_foreign');
        });

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->foreign('client_id')
                ->references('id')
                ->on('admins_bkk_24oct2025')
                ->onDelete('set null');
        });
    }
};
