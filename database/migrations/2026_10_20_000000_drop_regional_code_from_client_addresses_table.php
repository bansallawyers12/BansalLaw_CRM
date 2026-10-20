<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regional classification (migration-CRM concept) is not used in the
     * Legal CRM — addresses are saved without a regional code.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_addresses') && Schema::hasColumn('client_addresses', 'regional_code')) {
            Schema::table('client_addresses', function (Blueprint $table) {
                $table->dropColumn('regional_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client_addresses') && ! Schema::hasColumn('client_addresses', 'regional_code')) {
            Schema::table('client_addresses', function (Blueprint $table) {
                $table->string('regional_code', 50)->nullable();
            });
        }
    }
};
