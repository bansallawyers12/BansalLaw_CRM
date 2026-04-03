<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops 17 staff-only columns from admins table.
     * Staff data was migrated to staff table; admins now holds clients/leads only.
     */
    public function up(): void
    {
        $columnsToDrop = [
            'position',
            'team',
            'permission',
            'office_id',
            'show_dashboard_per',
            'time_zone',
            'is_migration_agent',
            'marn_number',
            'legal_practitioner_number',
            'company_name',
            'company_website',
            'business_address',
            'business_phone',
            'business_mobile',
            'business_email',
            'tax_number',
            'ABN_number',
        ];

        $existing = array_filter($columnsToDrop, fn (string $c) => Schema::hasColumn('admins', $c));
        if (empty($existing)) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) use ($existing) {
            // Drop indexes on is_migration_agent and marn_number before dropping columns
            if (in_array('is_migration_agent', $existing)) {
                try {
                    $table->dropIndex(['is_migration_agent']);
                } catch (\Exception $e) {
                    // Index may not exist, ignore
                }
            }
            if (in_array('marn_number', $existing)) {
                try {
                    $table->dropIndex(['marn_number']);
                } catch (\Exception $e) {
                    // Index may not exist, ignore
                }
            }

            $table->dropColumn(array_values($existing));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('position')->nullable();
            $table->string('team')->nullable();
            $table->text('permission')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->tinyInteger('show_dashboard_per')->default(0)->nullable();
            $table->string('time_zone', 50)->nullable();
            $table->tinyInteger('is_migration_agent')->default(0)->nullable();
            $table->string('marn_number', 100)->nullable();
            $table->string('legal_practitioner_number', 100)->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_website', 500)->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_phone', 100)->nullable();
            $table->string('business_mobile', 100)->nullable();
            $table->string('business_email')->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->string('ABN_number', 100)->nullable();
        });

        // Restore FK and indexes
        if (Schema::hasColumn('admins', 'office_id')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->foreign('office_id')->references('id')->on('branches')->onDelete('set null');
            });
        }
        if (Schema::hasColumn('admins', 'is_migration_agent')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->index('is_migration_agent');
            });
        }
        if (Schema::hasColumn('admins', 'marn_number')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->index('marn_number');
            });
        }
    }
};
