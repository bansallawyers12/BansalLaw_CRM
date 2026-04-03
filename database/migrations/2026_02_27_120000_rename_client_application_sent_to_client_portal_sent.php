<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename client_application_sent → client_portal_sent and
     * client_application_sent_at → client_portal_sent_at in account_client_receipts.
     */
    public function up(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('account_client_receipts', 'client_application_sent')) {
                $table->renameColumn('client_application_sent', 'client_portal_sent');
            }
            if (Schema::hasColumn('account_client_receipts', 'client_application_sent_at')) {
                $table->renameColumn('client_application_sent_at', 'client_portal_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('account_client_receipts', 'client_portal_sent')) {
                $table->renameColumn('client_portal_sent', 'client_application_sent');
            }
            if (Schema::hasColumn('account_client_receipts', 'client_portal_sent_at')) {
                $table->renameColumn('client_portal_sent_at', 'client_application_sent_at');
            }
        });
    }
};
