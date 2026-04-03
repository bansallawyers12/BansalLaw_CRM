<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds client portal payment fields for Billing invoice Update API (Google Pay / Apple Pay / Stripe).
     */
    public function up(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('account_client_receipts', 'client_portal_payment_token')) {
                $table->string('client_portal_payment_token', 500)->nullable();
            }
            if (!Schema::hasColumn('account_client_receipts', 'client_portal_payment_type')) {
                $table->string('client_portal_payment_type', 50)->nullable()->comment('google_pay, apple_pay, or stripe');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('account_client_receipts', 'client_portal_payment_token')) {
                $table->dropColumn('client_portal_payment_token');
            }
            if (Schema::hasColumn('account_client_receipts', 'client_portal_payment_type')) {
                $table->dropColumn('client_portal_payment_type');
            }
        });
    }
};
