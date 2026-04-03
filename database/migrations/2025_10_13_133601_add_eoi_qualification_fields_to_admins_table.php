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
        Schema::table('admins', function (Blueprint $table) {
            // Australian Study Requirement (2+ years in Australia)
            $table->boolean('australian_study')->default(0)->after('py_date')
                ->comment('Has Australian study requirement (2+ years)');
            $table->date('australian_study_date')->nullable()->after('australian_study')
                ->comment('Australian study completion date');
            
            // Specialist Education (STEM Masters/PhD by research)
            $table->boolean('specialist_education')->default(0)->after('australian_study_date')
                ->comment('Has specialist education qualification (STEM)');
            $table->date('specialist_education_date')->nullable()->after('specialist_education')
                ->comment('Specialist education completion date');
            
            // Regional Study (studied in regional Australia)
            $table->boolean('regional_study')->default(0)->after('specialist_education_date')
                ->comment('Has regional study (studied in regional Australia)');
            $table->date('regional_study_date')->nullable()->after('regional_study')
                ->comment('Regional study completion date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'australian_study',
                'australian_study_date',
                'specialist_education',
                'specialist_education_date',
                'regional_study',
                'regional_study_date'
            ]);
        });
    }
};
