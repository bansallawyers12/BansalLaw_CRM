<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4: Drop other legacy columns from admins table.
     * See docs/ADMINS_TABLE_COLUMNS.md Implementation Plan.
     */
    public function up(): void
    {
        $columnsToDrop = [
            'decrypt_password',
            'primary_email',
            'prev_visa',
            'is_visa_expire_mail_sent',
            'rating',
            'exempt_person_reason',
            'is_star_client',
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
            $stringCols = ['decrypt_password', 'primary_email', 'rating', 'exempt_person_reason', 'is_star_client'];
            foreach ($stringCols as $col) {
                if (!Schema::hasColumn('admins', $col)) {
                    $table->string($col)->nullable();
                }
            }
            if (!Schema::hasColumn('admins', 'prev_visa')) {
                $table->text('prev_visa')->nullable();
            }
            if (!Schema::hasColumn('admins', 'is_visa_expire_mail_sent')) {
                $table->tinyInteger('is_visa_expire_mail_sent')->nullable();
            }
        });
    }
};
