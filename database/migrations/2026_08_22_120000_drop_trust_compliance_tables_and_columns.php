<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove unused VLSB trust compliance module. Client funds ledger (receipt_type = 1)
 * remains for internal CRM tracking; legal trust accounting is handled in Smokeball.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('trust_bank_statement_lines');
        Schema::dropIfExists('trust_bank_accounts');
        Schema::dropIfExists('trust_withdrawal_authorities');
        Schema::dropIfExists('trust_withdrawal_authority_types');
        Schema::dropIfExists('trust_monthly_archives');
        Schema::dropIfExists('trust_accounting_periods');
        Schema::dropIfExists('trust_practice_sequences');
        Schema::dropIfExists('trust_audit_logs');

        if (Schema::hasTable('staff') && Schema::hasColumn('staff', 'trust_rule42_supervisor')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->dropColumn('trust_rule42_supervisor');
            });
        }

        if (Schema::hasTable('client_matters') && Schema::hasColumn('client_matters', 'trust_last_statement_sent_at')) {
            Schema::table('client_matters', function (Blueprint $table) {
                $table->dropColumn('trust_last_statement_sent_at');
            });
        }

        if (Schema::hasTable('account_client_receipts')) {
            Schema::table('account_client_receipts', function (Blueprint $table) {
                foreach ([
                    'trust_voided_at',
                    'trust_voided_by',
                    'trust_void_reason',
                    'trust_reversal_of_entry_id',
                    'payer_name',
                    'bank_deposit_reference',
                    'banking_date',
                    'payee_name',
                    'cheque_number',
                    'eft_account_name',
                    'eft_bsb',
                    'eft_account_number',
                ] as $col) {
                    if (Schema::hasColumn('account_client_receipts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Irreversible cleanup — trust compliance was never used in production.
    }
};
