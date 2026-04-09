<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL / stub installs may lack cost_assignment_forms (CRM checklists tab).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cost_assignment_forms')) {
            return;
        }

        Schema::create('cost_assignment_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('client_matter_id')->nullable()->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index();
            $table->string('surcharge', 32)->nullable();

            foreach ([
                'Dept_Base_Application_Charge',
                'Dept_Base_Application_Charge_no_of_person',
                'Dept_Base_Application_Charge_after_person',
                'Dept_Base_Application_Charge_after_person_surcharge',
                'Dept_Non_Internet_Application_Charge',
                'Dept_Non_Internet_Application_Charge_no_of_person',
                'Dept_Non_Internet_Application_Charge_after_person',
                'Dept_Non_Internet_Application_Charge_after_person_surcharge',
                'Dept_Additional_Applicant_Charge_18_Plus',
                'Dept_Additional_Applicant_Charge_18_Plus_no_of_person',
                'Dept_Additional_Applicant_Charge_18_Plus_after_person',
                'Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge',
                'Dept_Additional_Applicant_Charge_Under_18',
                'Dept_Additional_Applicant_Charge_Under_18_no_of_person',
                'Dept_Additional_Applicant_Charge_Under_18_after_person',
                'Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge',
                'Dept_Subsequent_Temp_Application_Charge',
                'Dept_Subsequent_Temp_Application_Charge_no_of_person',
                'Dept_Subsequent_Temp_Application_Charge_after_person',
                'Dept_Subsequent_Temp_Application_Charge_after_person_surcharge',
                'Dept_Second_VAC_Instalment_Charge_18_Plus',
                'Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person',
                'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person',
                'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge',
                'Dept_Second_VAC_Instalment_Under_18',
                'Dept_Second_VAC_Instalment_Under_18_no_of_person',
                'Dept_Second_VAC_Instalment_Under_18_after_person',
                'Dept_Second_VAC_Instalment_Under_18_after_person_surcharge',
                'Dept_Nomination_Application_Charge',
                'Dept_Sponsorship_Application_Charge',
                'Block_1_Ex_Tax',
                'Block_2_Ex_Tax',
                'Block_3_Ex_Tax',
                'additional_fee_1',
                'TotalDoHACharges',
                'TotalDoHASurcharges',
                'TotalBLOCKFEE',
            ] as $col) {
                $table->decimal($col, 15, 2)->nullable();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cost_assignment_forms')) {
            return;
        }

        Schema::drop('cost_assignment_forms');
    }
};
