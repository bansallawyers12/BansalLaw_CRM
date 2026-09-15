<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_all_invoice_receipts')) {
            return;
        }

        Schema::table('account_all_invoice_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('account_all_invoice_receipts', 'billing_basis')) {
                $table->string('billing_basis', 16)->nullable();
            }
            if (! Schema::hasColumn('account_all_invoice_receipts', 'hours')) {
                $table->decimal('hours', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('account_all_invoice_receipts', 'rate_ex_gst')) {
                $table->decimal('rate_ex_gst', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('account_all_invoice_receipts', 'amount_ex_gst')) {
                $table->decimal('amount_ex_gst', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('account_all_invoice_receipts', 'line_gst')) {
                $table->decimal('line_gst', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('account_all_invoice_receipts', 'fee_earner_id')) {
                $table->unsignedBigInteger('fee_earner_id')->nullable();
            }
            if (! Schema::hasColumn('account_all_invoice_receipts', 'fee_earner_role')) {
                $table->string('fee_earner_role', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_all_invoice_receipts')) {
            return;
        }

        Schema::table('account_all_invoice_receipts', function (Blueprint $table) {
            foreach ([
                'billing_basis',
                'hours',
                'rate_ex_gst',
                'amount_ex_gst',
                'line_gst',
                'fee_earner_id',
                'fee_earner_role',
            ] as $column) {
                if (Schema::hasColumn('account_all_invoice_receipts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
