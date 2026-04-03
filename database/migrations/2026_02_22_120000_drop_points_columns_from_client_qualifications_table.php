<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops specialist_education, stem_qualification, regional_study from client_qualifications.
     * Points calculation uses the admins table fields (specialist_education, regional_study) instead.
     */
    public function up(): void
    {
        Schema::table('client_qualifications', function (Blueprint $table) {
            $table->dropColumn(['specialist_education', 'stem_qualification', 'regional_study']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_qualifications', function (Blueprint $table) {
            $table->boolean('specialist_education')->default(0)->after('relevant_qualification');
            $table->boolean('stem_qualification')->default(0)->after('specialist_education');
            $table->boolean('regional_study')->default(0)->after('stem_qualification');
        });
    }
};
