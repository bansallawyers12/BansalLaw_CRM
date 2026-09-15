<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_all_invoice_receipts') && Schema::hasColumn('account_all_invoice_receipts', 'trans_date')) {
            DB::statement('ALTER TABLE account_all_invoice_receipts ALTER COLUMN trans_date TYPE VARCHAR(128)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('account_all_invoice_receipts') && Schema::hasColumn('account_all_invoice_receipts', 'trans_date')) {
            DB::statement('ALTER TABLE account_all_invoice_receipts ALTER COLUMN trans_date TYPE VARCHAR(32)');
        }
    }
};
