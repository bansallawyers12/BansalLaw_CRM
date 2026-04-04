<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Historical: updated rows in `anzsco_occupations` before that table was removed
     * by 2026_04_04_000001_remove_anzsco_occupation_system. No-op if the table is absent.
     */
    public function up(): void
    {
        if (!Schema::hasTable('anzsco_occupations')) {
            return;
        }

        DB::table('anzsco_occupations')
            ->whereIn('assessing_authority', ['ACS', 'ANMAC', 'AITSL'])
            ->update(['assessment_validity_years' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('anzsco_occupations')) {
            return;
        }

        DB::table('anzsco_occupations')
            ->whereIn('assessing_authority', ['ACS', 'ANMAC', 'AITSL'])
            ->update(['assessment_validity_years' => 3]);
    }
};

