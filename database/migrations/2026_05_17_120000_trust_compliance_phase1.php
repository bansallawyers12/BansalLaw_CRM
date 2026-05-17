<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trust compliance Phase 1: immutable ledger voids, audit log, practice receipt sequence,
     * trust-year periods (for locking), deposit metadata fields.
     */
    public function up(): void
    {
        if (Schema::hasTable('account_client_receipts')) {
            Schema::table('account_client_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('account_client_receipts', 'trust_voided_at')) {
                    $table->timestamp('trust_voided_at')->nullable()->after('updated_at');
                }
                if (! Schema::hasColumn('account_client_receipts', 'trust_voided_by')) {
                    $table->unsignedBigInteger('trust_voided_by')->nullable()->after('trust_voided_at');
                }
                if (! Schema::hasColumn('account_client_receipts', 'trust_void_reason')) {
                    $table->text('trust_void_reason')->nullable()->after('trust_voided_by');
                }
                if (! Schema::hasColumn('account_client_receipts', 'trust_reversal_of_entry_id')) {
                    $table->unsignedBigInteger('trust_reversal_of_entry_id')->nullable()->after('trust_void_reason');
                }
                if (! Schema::hasColumn('account_client_receipts', 'payer_name')) {
                    $table->string('payer_name', 255)->nullable()->after('payment_method');
                }
                if (! Schema::hasColumn('account_client_receipts', 'bank_deposit_reference')) {
                    $table->string('bank_deposit_reference', 191)->nullable()->after('payer_name');
                }
                if (! Schema::hasColumn('account_client_receipts', 'banking_date')) {
                    $table->string('banking_date', 32)->nullable()->after('bank_deposit_reference');
                }
            });
        }

        if (! Schema::hasTable('trust_audit_logs')) {
            Schema::create('trust_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('table_name', 128);
                $table->unsignedBigInteger('row_id');
                $table->string('event', 64);
                $table->string('field_name', 128)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('context')->nullable();
                $table->timestamps();
                $table->index(['table_name', 'row_id']);
                $table->index('performed_by');
            });
        }

        if (! Schema::hasTable('trust_practice_sequences')) {
            Schema::create('trust_practice_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('trust_year_start_year')->unique()->comment('Victorian trust year begins 1 April; value is calendar year of 1 April (e.g. 2025 for Apr 2025–Mar 2026)');
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trust_accounting_periods')) {
            Schema::create('trust_accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 32)->default('locked');
                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by_staff_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['period_start', 'period_end']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_accounting_periods');
        Schema::dropIfExists('trust_practice_sequences');
        Schema::dropIfExists('trust_audit_logs');

        if (Schema::hasTable('account_client_receipts')) {
            Schema::table('account_client_receipts', function (Blueprint $table) {
                foreach (
                    [
                        'banking_date',
                        'bank_deposit_reference',
                        'payer_name',
                        'trust_reversal_of_entry_id',
                        'trust_void_reason',
                        'trust_voided_by',
                        'trust_voided_at',
                    ] as $col
                ) {
                    if (Schema::hasColumn('account_client_receipts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
