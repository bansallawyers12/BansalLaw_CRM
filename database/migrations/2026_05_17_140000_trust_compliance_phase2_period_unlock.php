<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: trust period unlock trail (who reopened a closed period and why).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trust_accounting_periods')) {
            return;
        }

        Schema::table('trust_accounting_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('trust_accounting_periods', 'unlocked_at')) {
                $table->timestamp('unlocked_at')->nullable()->after('locked_by_staff_id');
            }
            if (! Schema::hasColumn('trust_accounting_periods', 'unlocked_by_staff_id')) {
                $table->unsignedBigInteger('unlocked_by_staff_id')->nullable()->after('unlocked_at');
            }
            if (! Schema::hasColumn('trust_accounting_periods', 'unlock_reason')) {
                $table->text('unlock_reason')->nullable()->after('unlocked_by_staff_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trust_accounting_periods')) {
            return;
        }

        Schema::table('trust_accounting_periods', function (Blueprint $table) {
            foreach (['unlock_reason', 'unlocked_by_staff_id', 'unlocked_at'] as $col) {
                if (Schema::hasColumn('trust_accounting_periods', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
