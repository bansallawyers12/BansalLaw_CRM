<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops the legacy clients table. Client data lives in admins table (type='client').
     * This table was only populated by SyncDataFromMySQL; MySQL sync is no longer needed.
     */
    public function up(): void
    {
        Schema::dropIfExists('clients');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Table structure not restored - clients table schema was legacy from BansalCRM.
        // Re-running SyncDataFromMySQL would repopulate from MySQL source if needed.
    }
};
