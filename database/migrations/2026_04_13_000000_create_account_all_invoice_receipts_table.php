<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Line-item rows for invoices (receipt_type = 3), keyed by receipt_id.
     * Parent summary lives in account_client_receipts; inserts use AccountAllInvoiceReceipt.
     */
    public function up(): void
    {
        if (Schema::hasTable('account_all_invoice_receipts')) {
            return;
        }

        Schema::create('account_all_invoice_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('client_matter_id')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable()->index();
            $table->tinyInteger('receipt_type')->nullable()->index();
            $table->string('trans_date', 32)->nullable();
            $table->string('entry_date', 32)->nullable();
            $table->string('trans_no', 191)->nullable();
            $table->string('gst_included', 32)->nullable();
            $table->string('payment_type', 191)->nullable();
            $table->text('description')->nullable();
            $table->decimal('withdraw_amount', 15, 2)->nullable();
            $table->decimal('balance_amount', 15, 2)->nullable();
            $table->string('invoice_no', 191)->nullable();
            $table->string('save_type', 32)->nullable();
            $table->tinyInteger('invoice_status')->default(0);
            $table->decimal('withdraw_amount_before_void', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_all_invoice_receipts');
    }
};
