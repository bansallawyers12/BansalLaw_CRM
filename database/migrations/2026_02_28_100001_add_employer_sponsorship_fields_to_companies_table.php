<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds employer sponsorship, trust, workforce, financial, LMT, training fields to companies.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Trust section (when company_type = Trust)
            $table->string('trust_name', 255)->nullable()->after('company_website');
            $table->string('trust_abn', 20)->nullable()->after('trust_name');
            $table->string('trustee_name', 255)->nullable()->after('trust_abn');
            $table->text('trustee_details')->nullable()->after('trustee_name');

            // Sponsorship
            $table->string('sponsorship_type', 50)->nullable()->after('trustee_details');
            $table->string('sponsorship_status', 50)->nullable()->after('sponsorship_type');
            $table->date('sponsorship_start_date')->nullable()->after('sponsorship_status');
            $table->date('sponsorship_end_date')->nullable()->after('sponsorship_start_date');
            $table->string('trn', 50)->nullable()->after('sponsorship_end_date')->comment('Training Reference Number');
            $table->boolean('regional_sponsorship')->nullable()->after('trn');
            $table->boolean('adverse_information')->nullable()->after('regional_sponsorship');
            $table->text('previous_sponsorship_notes')->nullable()->after('adverse_information');

            // Financial
            $table->decimal('annual_turnover', 15, 2)->nullable()->after('previous_sponsorship_notes');
            $table->decimal('wages_expenditure', 15, 2)->nullable()->after('annual_turnover');

            // Workforce counts
            $table->integer('workforce_australian_citizens')->nullable()->after('wages_expenditure');
            $table->integer('workforce_permanent_residents')->nullable()->after('workforce_australian_citizens');
            $table->integer('workforce_temp_visa_holders')->nullable()->after('workforce_permanent_residents');
            $table->integer('workforce_total')->nullable()->after('workforce_temp_visa_holders');

            // Operations
            $table->date('business_operating_since')->nullable()->after('workforce_total');
            $table->string('main_business_activity', 255)->nullable()->after('business_operating_since');

            // LMT (Labour Market Testing)
            $table->boolean('lmt_required')->nullable()->after('main_business_activity');
            $table->date('lmt_start_date')->nullable()->after('lmt_required');
            $table->date('lmt_end_date')->nullable()->after('lmt_start_date');
            $table->text('lmt_notes')->nullable()->after('lmt_end_date');

            // Training
            $table->string('training_position_title', 255)->nullable()->after('lmt_notes');
            $table->string('trainer_name', 255)->nullable()->after('training_position_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'trust_name', 'trust_abn', 'trustee_name', 'trustee_details',
                'sponsorship_type', 'sponsorship_status', 'sponsorship_start_date', 'sponsorship_end_date',
                'trn', 'regional_sponsorship', 'adverse_information', 'previous_sponsorship_notes',
                'annual_turnover', 'wages_expenditure',
                'workforce_australian_citizens', 'workforce_permanent_residents', 'workforce_temp_visa_holders', 'workforce_total',
                'business_operating_since', 'main_business_activity',
                'lmt_required', 'lmt_start_date', 'lmt_end_date', 'lmt_notes',
                'training_position_title', 'trainer_name',
            ]);
        });
    }
};
