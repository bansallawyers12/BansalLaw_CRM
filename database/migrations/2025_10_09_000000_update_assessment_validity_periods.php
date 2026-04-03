<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update assessment validity from 3 to 2 years for ACS, ANMAC, and AITSL
        DB::table('anzsco_occupations')
            ->whereIn('assessing_authority', ['ACS', 'ANMAC', 'AITSL'])
            ->update(['assessment_validity_years' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to 3 years if needed
        DB::table('anzsco_occupations')
            ->whereIn('assessing_authority', ['ACS', 'ANMAC', 'AITSL'])
            ->update(['assessment_validity_years' => 3]);
    }
};

