<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_legal_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('client_matter_id')->nullable();
            $table->unsignedBigInteger('created_by');

            $table->enum('form_type', ['short_costs_disclosure', 'cost_agreement', 'authority_to_act']);

            // Common fields
            $table->string('matter_reference')->nullable();

            // Law practice / firm fields
            $table->string('firm_name')->default('Bansal Lawyers');
            $table->string('firm_contact')->nullable();
            $table->text('firm_address')->default('Level 8, 278 Collins Street, Melbourne VIC 3000');
            $table->string('firm_phone')->default('0422 905 860');
            $table->string('firm_mobile')->nullable();
            $table->string('firm_email')->default('info@bansallawyers.com.au');
            $table->string('firm_state')->default('VIC');
            $table->string('firm_postcode')->default('3000');

            // Person responsible
            $table->string('person_responsible')->nullable();
            $table->string('person_responsible_email')->nullable();

            // Scope of work
            $table->text('scope_of_work')->nullable();

            // Costs (Short Costs Disclosure)
            $table->decimal('estimated_legal_fees', 10, 2)->default(0);
            $table->decimal('estimated_disbursements', 10, 2)->default(0);
            $table->decimal('estimated_barrister_fees', 10, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('estimated_total', 10, 2)->default(0);

            // Cost Agreement specific
            $table->string('fee_type')->nullable(); // 'fixed', 'hourly'
            $table->decimal('fixed_fee_amount', 10, 2)->default(0);
            $table->text('cost_estimate_breakdown')->nullable();
            $table->text('variables_affecting_costs')->nullable();

            // Payment arrangements
            $table->decimal('retainer_amount', 10, 2)->default(0);
            $table->string('trust_account_name')->default('BANSAL Lawyers');
            $table->string('trust_account_institution')->default('NAB');
            $table->string('trust_account_bsb')->default('083419');
            $table->string('trust_account_number')->default('787266100');
            $table->string('payment_reference')->nullable();

            // Authority to Act specific
            $table->text('authority_scope')->nullable();

            // PDF storage
            $table->string('pdf_path')->nullable();

            $table->date('form_date')->nullable();
            $table->date('signed_date')->nullable();

            $table->timestamps();

            $table->index('client_id');
            $table->index('client_matter_id');
            $table->index('form_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_legal_forms');
    }
};
