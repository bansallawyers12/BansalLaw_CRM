<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused companies columns with no live CRM UI
 * (sponsorship, financial, workforce, operations, LMT, training).
 * Trust fields are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $cols = array_values(array_filter([
            'sponsorship_type',
            'sponsorship_status',
            'sponsorship_start_date',
            'sponsorship_end_date',
            'trn',
            'regional_sponsorship',
            'adverse_information',
            'previous_sponsorship_notes',
            'annual_turnover',
            'wages_expenditure',
            'workforce_australian_citizens',
            'workforce_permanent_residents',
            'workforce_temp_visa_holders',
            'workforce_total',
            'workforce_foreign_494',
            'workforce_foreign_other_temp_activity',
            'workforce_foreign_overseas_students',
            'workforce_foreign_working_holiday',
            'workforce_foreign_other',
            'business_operating_since',
            'main_business_activity',
            'lmt_required',
            'lmt_start_date',
            'lmt_end_date',
            'lmt_notes',
            'training_position_title',
            'trainer_name',
        ], fn (string $c) => Schema::hasColumn('companies', $c)));

        if ($cols === []) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }

    public function down(): void
    {
        throw new \RuntimeException('Cannot reverse drop of unused companies employer columns.');
    }
};
