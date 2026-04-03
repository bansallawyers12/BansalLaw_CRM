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
        Schema::table('client_qualifications', function (Blueprint $table) {
            // Specialist education qualification (STEM Masters/PhD by research in Australia)
            $table->boolean('specialist_education')->default(0)->after('relevant_qualification')
                ->comment('STEM Masters or PhD by research in Australia (+10 points)');
            
            // STEM qualification indicator
            $table->boolean('stem_qualification')->default(0)->after('specialist_education')
                ->comment('Indicates if qualification is in STEM field');
            
            // Regional study in Australia
            $table->boolean('regional_study')->default(0)->after('stem_qualification')
                ->comment('Studied in regional Australia (+5 points)');
            
            // Add index for points calculation queries
            $table->index(['client_id', 'country'], 'idx_client_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_qualifications', function (Blueprint $table) {
            $table->dropIndex('idx_client_country');
            $table->dropColumn(['specialist_education', 'stem_qualification', 'regional_study']);
        });
    }
};
