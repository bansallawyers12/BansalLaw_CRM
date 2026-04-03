<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkin_logs', function (Blueprint $table) {
            $table->string('walk_in_phone', 32)->nullable()->after('client_id');
            $table->string('walk_in_email', 255)->nullable()->after('walk_in_phone');
        });
    }

    public function down(): void
    {
        Schema::table('checkin_logs', function (Blueprint $table) {
            $table->dropColumn(['walk_in_phone', 'walk_in_email']);
        });
    }
};
