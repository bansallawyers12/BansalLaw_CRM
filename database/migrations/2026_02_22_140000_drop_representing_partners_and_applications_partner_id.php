<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops the legacy representing_partners table and applications.partner_id column.
     * Partner & Branch feature was removed - getpartnerbranch returns "No partners available".
     */
    public function up(): void
    {
        if (Schema::hasTable('representing_partners')) {
            Schema::drop('representing_partners');
        }

        if (Schema::hasColumn('applications', 'partner_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('partner_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('representing_partners')) {
            Schema::create('representing_partners', function (Blueprint $table) {
                $table->id();
                $table->string('partner_name')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('applications', 'partner_id')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedBigInteger('partner_id')->nullable()->after('workflow');
            });
        }
    }
};
