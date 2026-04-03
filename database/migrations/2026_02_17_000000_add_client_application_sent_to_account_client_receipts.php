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
            if (!Schema::hasColumn('account_client_receipts', 'client_application_sent')) {
                $table->tinyInteger('client_application_sent')->default(0);
            }
            if (!Schema::hasColumn('account_client_receipts', 'client_application_sent_at')) {
                $table->timestamp('client_application_sent_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('account_client_receipts', 'client_application_sent_at')) {
                $table->dropColumn('client_application_sent_at');
            }
            if (Schema::hasColumn('account_client_receipts', 'client_application_sent')) {
                $table->dropColumn('client_application_sent');
            }
        });
    }
};
