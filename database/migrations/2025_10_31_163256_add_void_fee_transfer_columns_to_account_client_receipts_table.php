<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            // Check if columns don't already exist before adding
            if (!Schema::hasColumn('account_client_receipts', 'void_fee_transfer')) {
                $table->tinyInteger('void_fee_transfer')->default(0)->nullable()->after('void_invoice');
            }
            if (!Schema::hasColumn('account_client_receipts', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('void_fee_transfer');
            }
            if (!Schema::hasColumn('account_client_receipts', 'voided_by')) {
                $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('account_client_receipts', 'void_fee_transfer')) {
                $table->dropColumn('void_fee_transfer');
            }
            if (Schema::hasColumn('account_client_receipts', 'voided_at')) {
                $table->dropColumn('voided_at');
            }
            if (Schema::hasColumn('account_client_receipts', 'voided_by')) {
                $table->dropColumn('voided_by');
            }
        });
    }
};
