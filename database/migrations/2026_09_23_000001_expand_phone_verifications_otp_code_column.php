<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('phone_verifications') || ! Schema::hasColumn('phone_verifications', 'otp_code')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE phone_verifications ALTER COLUMN otp_code TYPE VARCHAR(255)');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE phone_verifications MODIFY COLUMN otp_code VARCHAR(255) NOT NULL');
        } else {
            Schema::table('phone_verifications', function (Blueprint $table) {
                $table->string('otp_code', 255)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('phone_verifications') || ! Schema::hasColumn('phone_verifications', 'otp_code')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE phone_verifications ALTER COLUMN otp_code TYPE VARCHAR(6)');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE phone_verifications MODIFY COLUMN otp_code VARCHAR(6) NOT NULL');
        } else {
            Schema::table('phone_verifications', function (Blueprint $table) {
                $table->string('otp_code', 6)->change();
            });
        }
    }
};
