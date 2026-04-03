<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2: Drop alternative contact columns (att_email, att_phone, att_country_code) from admins table.
     * See docs/ADMINS_TABLE_COLUMNS.md Implementation Plan.
     */
    public function up(): void
    {
        $columnsToDrop = [
            'att_email',
            'att_phone',
            'att_country_code',
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
            if (!Schema::hasColumn('admins', 'att_email')) {
                $table->string('att_email')->nullable();
            }
            if (!Schema::hasColumn('admins', 'att_phone')) {
                $table->string('att_phone')->nullable();
            }
            if (!Schema::hasColumn('admins', 'att_country_code')) {
                $table->string('att_country_code')->nullable();
            }
        });
    }
};
