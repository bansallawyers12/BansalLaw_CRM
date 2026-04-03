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
        Schema::table('admins', function (Blueprint $table) {
            // Remove manual email verification fields
            $table->dropColumn([
                'email_verified_date',
                'email_verified_by', 
                'manual_email_phone_verified'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            // Add back the manual email verification fields
            $table->timestamp('email_verified_date')->nullable()->after('email');
            $table->unsignedInteger('email_verified_by')->nullable()->after('email_verified_date');
            $table->tinyInteger('manual_email_phone_verified')->default(0)->after('email_verified_by');
        });
    }
};