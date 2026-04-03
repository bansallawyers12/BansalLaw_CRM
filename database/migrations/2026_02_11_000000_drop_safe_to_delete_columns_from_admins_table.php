<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 0: Drop columns with no code references from admins table.
     * See docs/ADMINS_TABLE_COLUMNS.md Implementation Plan.
     */
    public function up(): void
    {
        $columnsToDrop = [
            'staff_id',
            'tags',
            'wp_customer_id',
            'applications',
            'smtp_host',
            'smtp_port',
            'smtp_enc',
            'smtp_username',
            'smtp_password',
            'experience_job_title',
            'experience_country',
            'latitude',
            'longitude',
            'visa_opt',
            'followers',
            'preferredIntake',
        ];

        $existing = array_filter($columnsToDrop, fn (string $c) => Schema::hasColumn('admins', $c));
        if (!empty($existing)) {
            Schema::table('admins', function (Blueprint $table) use ($existing) {
                $table->dropColumn(array_values($existing));
            });
        }
    }

    /**
     * Reverse the migrations. Restores columns with nullable types.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'staff_id')) {
                $table->string('staff_id')->nullable();
            }
            if (!Schema::hasColumn('admins', 'tags')) {
                $table->text('tags')->nullable();
            }
            if (!Schema::hasColumn('admins', 'wp_customer_id')) {
                $table->string('wp_customer_id')->nullable();
            }
            if (!Schema::hasColumn('admins', 'applications')) {
                $table->text('applications')->nullable();
            }
            if (!Schema::hasColumn('admins', 'smtp_host')) {
                $table->string('smtp_host')->nullable();
            }
            if (!Schema::hasColumn('admins', 'smtp_port')) {
                $table->integer('smtp_port')->nullable();
            }
            if (!Schema::hasColumn('admins', 'smtp_enc')) {
                $table->string('smtp_enc')->nullable();
            }
            if (!Schema::hasColumn('admins', 'smtp_username')) {
                $table->string('smtp_username')->nullable();
            }
            if (!Schema::hasColumn('admins', 'smtp_password')) {
                $table->string('smtp_password')->nullable();
            }
            if (!Schema::hasColumn('admins', 'experience_job_title')) {
                $table->string('experience_job_title')->nullable();
            }
            if (!Schema::hasColumn('admins', 'experience_country')) {
                $table->string('experience_country')->nullable();
            }
            if (!Schema::hasColumn('admins', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('admins', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('admins', 'visa_opt')) {
                $table->string('visa_opt')->nullable();
            }
            if (!Schema::hasColumn('admins', 'followers')) {
                $table->text('followers')->nullable();
            }
            if (!Schema::hasColumn('admins', 'preferredIntake')) {
                $table->date('preferredIntake')->nullable();
            }
        });
    }
};
