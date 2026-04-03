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
        Schema::table('client_testscore', function (Blueprint $table) {
            $table->string('proficiency_level')->nullable()->after('overall_score')->comment('Calculated English proficiency level (e.g., Competent English, Proficient English, Superior English)');
            $table->integer('proficiency_points')->nullable()->after('proficiency_level')->comment('Points awarded for this proficiency level (0, 10, or 20)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_testscore', function (Blueprint $table) {
            $table->dropColumn(['proficiency_level', 'proficiency_points']);
        });
    }
};