<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused company child tables (no active edit UI; empty in practice).
 * Legacy companies.* sponsorship columns are left in place for now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('company_sponsorships');
        Schema::dropIfExists('company_nominations');
    }

    public function down(): void
    {
        throw new \RuntimeException('Cannot reverse drop of company_sponsorships / company_nominations.');
    }
};
