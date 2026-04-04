<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add CRM receipt columns missing when the table was created from stubs
     * or partial migrations (e.g. receipt_type, client_id, ledger amounts).
     *
     * trans_date / entry_date are VARCHAR (dd/mm/yyyy) — see applyDateFilters(),
     * FinancialStatsService, and PostgreSQL TO_DATE(trans_date, 'DD/MM/YYYY').
     */
    public function up(): void
    {
        if (! Schema::hasTable('account_client_receipts')) {
            return;
        }

        Schema::table('account_client_receipts', function (Blueprint $table) {
            $add = function (Blueprint $table, string $column, callable $define): void {
                if (! Schema::hasColumn('account_client_receipts', $column)) {
                    $define($table);
                }
            };

            $add($table, 'user_id', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable());
            $add($table, 'client_id', fn (Blueprint $t) => $t->unsignedBigInteger('client_id')->nullable());
            $add($table, 'client_matter_id', fn (Blueprint $t) => $t->unsignedBigInteger('client_matter_id')->nullable());
            $add($table, 'receipt_id', fn (Blueprint $t) => $t->unsignedBigInteger('receipt_id')->nullable());
            $add($table, 'receipt_type', fn (Blueprint $t) => $t->tinyInteger('receipt_type')->nullable()->index());
            $add($table, 'trans_date', fn (Blueprint $t) => $t->string('trans_date', 32)->nullable());
            $add($table, 'entry_date', fn (Blueprint $t) => $t->string('entry_date', 32)->nullable());
            $add($table, 'invoice_no', fn (Blueprint $t) => $t->string('invoice_no', 191)->nullable());
            $add($table, 'trans_no', fn (Blueprint $t) => $t->string('trans_no', 191)->nullable());
            $add($table, 'client_fund_ledger_type', fn (Blueprint $t) => $t->string('client_fund_ledger_type', 191)->nullable());
            $add($table, 'description', fn (Blueprint $t) => $t->text('description')->nullable());
            $add($table, 'deposit_amount', fn (Blueprint $t) => $t->decimal('deposit_amount', 15, 2)->nullable());
            $add($table, 'withdraw_amount', fn (Blueprint $t) => $t->decimal('withdraw_amount', 15, 2)->nullable());
            $add($table, 'balance_amount', fn (Blueprint $t) => $t->decimal('balance_amount', 15, 2)->nullable());
            $add($table, 'payment_method', fn (Blueprint $t) => $t->string('payment_method', 191)->nullable());
            $add($table, 'uploaded_doc_id', fn (Blueprint $t) => $t->unsignedBigInteger('uploaded_doc_id')->nullable());
            $add($table, 'validate_receipt', fn (Blueprint $t) => $t->tinyInteger('validate_receipt')->default(0));
            $add($table, 'void_invoice', fn (Blueprint $t) => $t->tinyInteger('void_invoice')->default(0));
            $add($table, 'invoice_status', fn (Blueprint $t) => $t->tinyInteger('invoice_status')->default(0));
            $add($table, 'save_type', fn (Blueprint $t) => $t->string('save_type', 32)->nullable());
            $add($table, 'hubdoc_sent', fn (Blueprint $t) => $t->boolean('hubdoc_sent')->default(false));
            $add($table, 'hubdoc_sent_at', fn (Blueprint $t) => $t->timestamp('hubdoc_sent_at')->nullable());
            $add($table, 'extra_amount_receipt', fn (Blueprint $t) => $t->string('extra_amount_receipt', 64)->nullable());
            $add($table, 'gst_included', fn (Blueprint $t) => $t->string('gst_included', 32)->nullable());
            $add($table, 'payment_type', fn (Blueprint $t) => $t->string('payment_type', 191)->nullable());
            $add($table, 'agent_id', fn (Blueprint $t) => $t->unsignedBigInteger('agent_id')->nullable());
            $add($table, 'voided_or_validated_by', fn (Blueprint $t) => $t->unsignedBigInteger('voided_or_validated_by')->nullable());
            $add($table, 'partial_paid_amount', fn (Blueprint $t) => $t->decimal('partial_paid_amount', 15, 2)->nullable());
            $add($table, 'withdraw_amount_before_void', fn (Blueprint $t) => $t->decimal('withdraw_amount_before_void', 15, 2)->nullable());
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_client_receipts')) {
            return;
        }

        $cols = [
            'withdraw_amount_before_void',
            'partial_paid_amount',
            'voided_or_validated_by',
            'agent_id',
            'payment_type',
            'gst_included',
            'extra_amount_receipt',
            'hubdoc_sent_at',
            'hubdoc_sent',
            'save_type',
            'invoice_status',
            'void_invoice',
            'validate_receipt',
            'uploaded_doc_id',
            'payment_method',
            'balance_amount',
            'withdraw_amount',
            'deposit_amount',
            'description',
            'client_fund_ledger_type',
            'trans_no',
            'invoice_no',
            'entry_date',
            'trans_date',
            'receipt_type',
            'receipt_id',
            'client_matter_id',
            'client_id',
            'user_id',
        ];

        Schema::table('account_client_receipts', function (Blueprint $table) use ($cols) {
            foreach ($cols as $c) {
                if (Schema::hasColumn('account_client_receipts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
