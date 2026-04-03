<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('account_client_receipts', 'eftpos_surcharge_amount')) {
                $table->decimal('eftpos_surcharge_amount', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('account_client_receipts', 'eftpos_surcharge_amount')) {
                $table->dropColumn('eftpos_surcharge_amount');
            }
        });
    }
};
