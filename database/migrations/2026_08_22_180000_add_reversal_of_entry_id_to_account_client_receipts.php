<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_client_receipts')) {
            return;
        }

        if (! Schema::hasColumn('account_client_receipts', 'reversal_of_entry_id')) {
            Schema::table('account_client_receipts', function (Blueprint $table) {
                $table->unsignedBigInteger('reversal_of_entry_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('account_client_receipts') && Schema::hasColumn('account_client_receipts', 'reversal_of_entry_id')) {
            Schema::table('account_client_receipts', function (Blueprint $table) {
                $table->dropColumn('reversal_of_entry_id');
            });
        }
    }
};
