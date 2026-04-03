<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('anzsco_occupations', function (Blueprint $table) {
            $table->id();
            $table->string('anzsco_code', 10)->unique()->index()->comment('6-digit ANZSCO code');
            $table->string('occupation_title')->index()->comment('Official occupation title');
            $table->string('occupation_title_normalized')->index()->nullable()->comment('Lowercase normalized title for searching');
            $table->tinyInteger('skill_level')->nullable()->comment('ANZSCO skill level 1-5');
            
            // Occupation Lists (boolean flags for multiple list membership)
            $table->boolean('is_on_mltssl')->default(false)->comment('Medium and Long-term Strategic Skills List');
            $table->boolean('is_on_stsol')->default(false)->comment('Short-term Skilled Occupation List');
            $table->boolean('is_on_rol')->default(false)->comment('Regional Occupation List');
            $table->boolean('is_on_csol')->default(false)->comment('Consolidated Sponsored Occupation List (legacy)');
            
            // Skill Assessment Details
            $table->string('assessing_authority')->nullable()->comment('e.g., ACS, VETASSESS, TRA');
            $table->integer('assessment_validity_years')->default(3)->comment('Years the assessment is valid');
            
            // Additional Information
            $table->text('additional_info')->nullable()->comment('Extra notes, requirements, or conditions');
            $table->text('alternate_titles')->nullable()->comment('Other common names for this occupation');
            
            // Status and Audit
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Indexes for search performance
            $table->index(['is_active', 'anzsco_code']);
            $table->index(['is_active', 'occupation_title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anzsco_occupations');
    }
};

