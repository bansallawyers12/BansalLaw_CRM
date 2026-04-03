<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops super_agent and sub_agent columns from applications table.
     * Super/Sub agents feature for applications has been removed.
     */
    public function up(): void
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (Schema::hasColumn('applications', 'super_agent')) {
                    $table->dropColumn('super_agent');
                }
                if (Schema::hasColumn('applications', 'sub_agent')) {
                    $table->dropColumn('sub_agent');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('applications')) {
            Schema::table('applications', function (Blueprint $table) {
                if (!Schema::hasColumn('applications', 'super_agent')) {
                    $table->integer('super_agent')->nullable()->after('expect_win_date');
                }
                if (!Schema::hasColumn('applications', 'sub_agent')) {
                    $table->integer('sub_agent')->nullable()->after('super_agent');
                }
            });
        }
    }
};
