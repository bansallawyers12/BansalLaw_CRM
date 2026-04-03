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
        Schema::table('client_spouse_details', function (Blueprint $table) {
            // Partner is Australian citizen
            $table->boolean('is_citizen')->default(0)->after('spouse_assessment_date')
                ->comment('Partner is Australian citizen');
            
            // Partner has Australian Permanent Residency
            $table->boolean('has_pr')->default(0)->after('is_citizen')
                ->comment('Partner has Australian Permanent Residency (PR)');
            
            // Partner DOB for age calculation
            $table->date('dob')->nullable()->after('has_pr')
                ->comment('Partner date of birth for points calculation');
            
            // Add index for points calculation queries
            $table->index('client_id', 'idx_spouse_client');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_spouse_details', function (Blueprint $table) {
            $table->dropIndex('idx_spouse_client');
            $table->dropColumn(['is_citizen', 'has_pr', 'dob']);
        });
    }
};
