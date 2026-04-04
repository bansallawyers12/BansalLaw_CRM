<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove the ANZSCO occupation reference system.
     *
     * Drops anzsco_occupation_id FK columns from client_eoi_references and
     * client_occupations, then drops the anzsco_occupations table entirely.
     * Company nominations (anzsco_code plain-text column) are intentionally preserved.
     */
    public function up(): void
    {
        // 1. Drop FK + column from client_eoi_references
        if (Schema::hasColumn('client_eoi_references', 'anzsco_occupation_id')) {
            Schema::table('client_eoi_references', function (Blueprint $table) {
                // Postgres and MySQL differ on FK drop syntax; try both safely
                try {
                    $table->dropForeign(['anzsco_occupation_id']);
                } catch (\Throwable $e) {
                    // FK may already be gone or named differently — continue
                }
                $table->dropColumn('anzsco_occupation_id');
            });
        }

        // 2. Drop FK + column from client_occupations
        if (Schema::hasColumn('client_occupations', 'anzsco_occupation_id')) {
            Schema::table('client_occupations', function (Blueprint $table) {
                try {
                    $table->dropForeign(['anzsco_occupation_id']);
                } catch (\Throwable $e) {
                    // FK may already be gone — continue
                }
                $table->dropColumn('anzsco_occupation_id');
            });
        }

        // 3. Drop the anzsco_occupations master table
        Schema::dropIfExists('anzsco_occupations');
    }

    /**
     * Reverse the migration (best-effort restore for development).
     */
    public function down(): void
    {
        // Re-create the anzsco_occupations table
        Schema::create('anzsco_occupations', function (Blueprint $table) {
            $table->id();
            $table->string('anzsco_code', 10)->unique()->index()->comment('6-digit ANZSCO code');
            $table->string('occupation_title')->index()->comment('Official occupation title');
            $table->string('occupation_title_normalized')->index()->nullable()->comment('Lowercase normalized title for searching');
            $table->tinyInteger('skill_level')->nullable()->comment('ANZSCO skill level 1-5');
            $table->boolean('is_on_mltssl')->default(false)->comment('Medium and Long-term Strategic Skills List');
            $table->boolean('is_on_stsol')->default(false)->comment('Short-term Skilled Occupation List');
            $table->boolean('is_on_rol')->default(false)->comment('Regional Occupation List');
            $table->boolean('is_on_csol')->default(false)->comment('Consolidated Sponsored Occupation List (legacy)');
            $table->string('assessing_authority')->nullable()->comment('e.g., ACS, VETASSESS, TRA');
            $table->integer('assessment_validity_years')->nullable()->comment('Years the assessment is valid');
            $table->text('additional_info')->nullable()->comment('Extra notes, requirements, or conditions');
            $table->text('alternate_titles')->nullable()->comment('Other common names for this occupation');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'anzsco_code']);
            $table->index(['is_active', 'occupation_title']);
        });

        // Re-add FK column to client_occupations
        if (!Schema::hasColumn('client_occupations', 'anzsco_occupation_id')) {
            Schema::table('client_occupations', function (Blueprint $table) {
                $table->unsignedBigInteger('anzsco_occupation_id')->nullable();
                $table->foreign('anzsco_occupation_id')
                      ->references('id')
                      ->on('anzsco_occupations')
                      ->onDelete('set null');
            });
        }

        // Re-add FK column to client_eoi_references
        if (!Schema::hasColumn('client_eoi_references', 'anzsco_occupation_id')) {
            Schema::table('client_eoi_references', function (Blueprint $table) {
                $table->unsignedBigInteger('anzsco_occupation_id')->nullable();
                $table->foreign('anzsco_occupation_id')
                      ->references('id')
                      ->on('anzsco_occupations')
                      ->onDelete('set null');
            });
        }
    }
};
