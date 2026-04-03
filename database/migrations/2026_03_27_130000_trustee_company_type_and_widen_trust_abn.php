<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename Business Type "Trust" to "Trustee" and widen trust_abn for combined ABN/ACN text.
     */
    public function up(): void
    {
        DB::table('companies')->where('company_type', 'Trust')->update(['company_type' => 'Trustee']);

        Schema::table('companies', function (Blueprint $table) {
            $table->string('trust_abn', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('trust_abn', 20)->nullable()->change();
        });

        DB::table('companies')->where('company_type', 'Trustee')->update(['company_type' => 'Trust']);
    }
};
