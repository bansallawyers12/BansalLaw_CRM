<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove visa_country column - we only track Australian visas and use
     * client's country_passport (Country of Passport) instead.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_visa_countries') && Schema::hasColumn('client_visa_countries', 'visa_country')) {
            Schema::table('client_visa_countries', function (Blueprint $table) {
                $table->dropColumn('visa_country');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('client_visa_countries') && !Schema::hasColumn('client_visa_countries', 'visa_country')) {
            Schema::table('client_visa_countries', function (Blueprint $table) {
                $table->string('visa_country', 255)->nullable()->after('admin_id');
            });
        }
    }
};
