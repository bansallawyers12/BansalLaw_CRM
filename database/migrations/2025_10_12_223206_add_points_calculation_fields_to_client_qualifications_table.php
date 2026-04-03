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
            if (! Schema::hasColumn('client_qualifications', 'specialist_education')) {
                $table->boolean('specialist_education')->default(0)->after('relevant_qualification')
                    ->comment('STEM Masters or PhD by research in Australia (+10 points)');
            }
            if (! Schema::hasColumn('client_qualifications', 'stem_qualification')) {
                $table->boolean('stem_qualification')->default(0)->after('specialist_education')
                    ->comment('Indicates if qualification is in STEM field');
            }
            if (! Schema::hasColumn('client_qualifications', 'regional_study')) {
                $table->boolean('regional_study')->default(0)->after('stem_qualification')
                    ->comment('Studied in regional Australia (+5 points)');
            }
        });

        if (! Schema::hasIndex('client_qualifications', 'idx_client_country')) {
            Schema::table('client_qualifications', function (Blueprint $table) {
                $table->index(['client_id', 'country'], 'idx_client_country');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('client_qualifications', 'idx_client_country')) {
            Schema::table('client_qualifications', function (Blueprint $table) {
                $table->dropIndex('idx_client_country');
            });
        }

        $cols = array_values(array_filter(
            ['specialist_education', 'stem_qualification', 'regional_study'],
            fn (string $c) => Schema::hasColumn('client_qualifications', $c)
        ));
        if ($cols !== []) {
            Schema::table('client_qualifications', function (Blueprint $table) use ($cols) {
                $table->dropColumn($cols);
            });
        }
    }
};
