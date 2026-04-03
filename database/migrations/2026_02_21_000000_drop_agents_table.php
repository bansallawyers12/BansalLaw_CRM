<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops the agents table - external agents feature was never fully implemented.
     * Super Agent / Sub Agent functionality uses agent_details table instead.
     */
    public function up(): void
    {
        Schema::dropIfExists('agents');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->string('agent_type')->nullable();
            $table->string('related_office')->nullable();
            $table->string('struture')->nullable();
            $table->string('business_name')->nullable();
            $table->string('tax_number')->nullable();
            $table->date('contract_expiry_date')->nullable();
            $table->string('country_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('income_sharing')->nullable();
            $table->string('claim_revenue')->nullable();
            $table->integer('is_acrchived')->default(0);
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
