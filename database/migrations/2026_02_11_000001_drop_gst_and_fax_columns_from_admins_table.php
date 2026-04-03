<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1: Drop GST and business/company fax columns from admins table.
     * See docs/ADMINS_TABLE_COLUMNS.md Implementation Plan.
     */
    public function up(): void
    {
        $columnsToDrop = [
            'gst_no',
            'is_business_gst',
            'gstin',
            'gst_date',
            'company_fax',
            'business_fax',
        ];

        $existing = array_filter($columnsToDrop, fn (string $c) => Schema::hasColumn('admins', $c));
        if (!empty($existing)) {
            Schema::table('admins', function (Blueprint $table) use ($existing) {
                $table->dropColumn(array_values($existing));
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'gst_no')) {
                $table->string('gst_no')->nullable();
            }
            if (!Schema::hasColumn('admins', 'is_business_gst')) {
                $table->string('is_business_gst')->nullable();
            }
            if (!Schema::hasColumn('admins', 'gstin')) {
                $table->string('gstin')->nullable();
            }
            if (!Schema::hasColumn('admins', 'gst_date')) {
                $table->string('gst_date')->nullable();
            }
            if (!Schema::hasColumn('admins', 'company_fax')) {
                $table->string('company_fax')->nullable();
            }
            if (!Schema::hasColumn('admins', 'business_fax')) {
                $table->string('business_fax')->nullable();
            }
        });
    }
};
