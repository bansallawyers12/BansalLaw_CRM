<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External agent_details records (receipt modals; distinct from staff). Required by receipt modals on client detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_details')) {
            return;
        }

        Schema::create('agent_details', function (Blueprint $table) {
            $table->id();
            $table->string('business_name', 255)->nullable();
            $table->string('agent_name', 255)->nullable()->index();
            $table->string('full_name', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('marn_number', 128)->nullable();
            $table->string('legal_practitioner_number', 128)->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_phone', 64)->nullable();
            $table->string('business_mobile', 64)->nullable();
            $table->string('business_email', 255)->nullable();
            $table->string('agent_type', 64)->nullable();
            $table->string('related_office', 255)->nullable();
            $table->string('struture', 255)->nullable();
            $table->string('tax_number', 128)->nullable();
            $table->date('contract_expiry_date')->nullable();
            $table->tinyInteger('is_acrchived')->nullable();
            $table->tinyInteger('status')->default(1)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('agent_details')) {
            return;
        }

        Schema::drop('agent_details');
    }
};
