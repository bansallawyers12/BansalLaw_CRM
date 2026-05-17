<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: practice trust bank accounts, imported/manual statement lines,
 * optional match to trust ledger rows (Rule 48 reconciliation support).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff') || ! Schema::hasTable('account_client_receipts')) {
            return;
        }

        Schema::create('trust_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bsb', 16)->nullable();
            $table->string('account_number_hint', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trust_bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trust_bank_account_id')->constrained('trust_bank_accounts')->cascadeOnDelete();
            $table->date('value_date');
            /** Positive = credit to the trust bank account (money in); negative = debit (money out). */
            $table->decimal('amount', 14, 2);
            $table->text('narrative')->nullable();
            $table->string('bank_reference', 500)->nullable();
            $table->unsignedBigInteger('matched_account_client_receipt_id')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->unsignedBigInteger('matched_by_staff_id')->nullable();
            $table->text('match_notes')->nullable();
            $table->timestamps();

            $table->index(['trust_bank_account_id', 'value_date']);

            $table->unique(['matched_account_client_receipt_id']);

            $table->foreign('matched_account_client_receipt_id')
                ->references('id')
                ->on('account_client_receipts')
                ->nullOnDelete();

            $table->foreign('matched_by_staff_id')
                ->references('id')
                ->on('staff')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_bank_statement_lines');
        Schema::dropIfExists('trust_bank_accounts');
    }
};
