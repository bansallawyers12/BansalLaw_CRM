<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace immigration-specific "Department Fee" columns with a flexible
 * disbursement_lines child table.
 *
 * cost_assignment_forms: drop all Dept_* / surcharge / TotalDoHA* columns,
 *   add TotalDisbursements.
 * matters: drop all Dept_* / surcharge default columns.
 * disbursement_lines: create new child table.
 */
return new class extends Migration
{
    private array $deptCols = [
        'surcharge',
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
        'TotalDoHACharges',
        'TotalDoHASurcharges',
    ];

    public function up(): void
    {
        // ── 1. cost_assignment_forms ──────────────────────────────────────
        if (Schema::hasTable('cost_assignment_forms')) {
            Schema::table('cost_assignment_forms', function (Blueprint $table) {
                foreach ($this->deptCols as $col) {
                    if (Schema::hasColumn('cost_assignment_forms', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });

            if (! Schema::hasColumn('cost_assignment_forms', 'TotalDisbursements')) {
                Schema::table('cost_assignment_forms', function (Blueprint $table) {
                    $table->decimal('TotalDisbursements', 15, 2)->nullable()->default(0)->after('TotalBLOCKFEE');
                });
            }
        }

        // ── 2. matters — drop Dept_* / surcharge default columns ─────────
        // NOTE: Block_* and additional_fee_1 columns are intentionally kept on
        // matters so AdminConsole can still store default fee templates per matter type.
        // Only the immigration-specific Dept_* / surcharge / TotalDoHA* columns are removed.
        if (Schema::hasTable('matters')) {
            $matterDeptCols = array_merge(
                ['surcharge'],
                array_filter($this->deptCols, fn($c) => str_starts_with($c, 'Dept_')),
                ['TotalDoHACharges', 'TotalDoHASurcharges'],
            );
            Schema::table('matters', function (Blueprint $table) use ($matterDeptCols) {
                foreach ($matterDeptCols as $col) {
                    if (Schema::hasColumn('matters', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // ── 3. disbursement_lines ─────────────────────────────────────────
        if (! Schema::hasTable('disbursement_lines')) {
            Schema::create('disbursement_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cost_assignment_form_id')->index();
                $table->string('nature', 64);          // court_fees, barrister_fees, …
                $table->string('description')->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('cost_assignment_form_id')
                      ->references('id')->on('cost_assignment_forms')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_lines');

        if (Schema::hasTable('cost_assignment_forms')) {
            if (Schema::hasColumn('cost_assignment_forms', 'TotalDisbursements')) {
                Schema::table('cost_assignment_forms', function (Blueprint $table) {
                    $table->dropColumn('TotalDisbursements');
                });
            }
        }
    }
};
