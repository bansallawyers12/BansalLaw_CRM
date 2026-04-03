<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops unused columns from staff table (per review):
     * - is_archived, archived_by, archived_on: archive flow applies to clients/leads in admins, not staff
     * - telephone: redundant with phone + country_code
     * - profile_img: replaced with static avatar.png (accessor on Staff model)
     * - verified: no staff-specific usage
     * - ABN_number: StaffController uses tax_number only; form maps to tax_number
     *
     * Keeps: tax_number (used for migration agents), remember_token (Laravel auth)
     */
    public function up(): void
    {
        if (!Schema::hasTable('staff')) {
            return;
        }

        // Drop archived_by FK first (self-referential)
        if (Schema::hasColumn('staff', 'archived_by')) {
            try {
                Schema::table('staff', function (Blueprint $table) {
                    $table->dropForeign(['archived_by']);
                });
            } catch (\Throwable $e) {
                // FK may have different name or already dropped
                if (str_contains($e->getMessage(), 'foreign key') || str_contains($e->getMessage(), 'does not exist')) {
                    // Ignore - column drop will handle it
                } else {
                    throw $e;
                }
            }
        }

        $columnsToDrop = [
            'is_archived',
            'archived_by',
            'archived_on',
            'telephone',
            'profile_img',
            'verified',
            'ABN_number',
        ];

        $existing = array_filter($columnsToDrop, fn (string $c) => Schema::hasColumn('staff', $c));
        if (!empty($existing)) {
            Schema::table('staff', function (Blueprint $table) use ($existing) {
                $table->dropColumn(array_values($existing));
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'verified')) {
                $table->tinyInteger('verified')->default(0)->after('status');
            }
            if (!Schema::hasColumn('staff', 'telephone')) {
                $table->string('telephone', 100)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('staff', 'profile_img')) {
                $table->string('profile_img', 500)->nullable()->after('telephone');
            }
            if (!Schema::hasColumn('staff', 'ABN_number')) {
                $table->string('ABN_number', 100)->nullable()->after('tax_number');
            }
            if (!Schema::hasColumn('staff', 'is_archived')) {
                $table->tinyInteger('is_archived')->default(0)->after('ABN_number');
            }
            if (!Schema::hasColumn('staff', 'archived_by')) {
                $table->unsignedBigInteger('archived_by')->nullable()->after('is_archived');
            }
            if (!Schema::hasColumn('staff', 'archived_on')) {
                $table->timestamp('archived_on')->nullable()->after('archived_by');
            }
        });

        // Restore archived_by FK
        if (Schema::hasColumn('staff', 'archived_by')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->foreign('archived_by')->references('id')->on('staff')->onDelete('set null');
            });
        }
    }
};
