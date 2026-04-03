<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Rename user_logs to staff_login_logs (Staff/Client/Lead terminology).
     */
    public function up(): void
    {
        if (Schema::hasTable('user_logs')) {
            Schema::rename('user_logs', 'staff_login_logs');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('staff_login_logs')) {
            Schema::rename('staff_login_logs', 'user_logs');
        }
    }
};
