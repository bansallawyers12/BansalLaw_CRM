<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub client_testscore may only have id, overall_score, timestamps.
 * Add columns expected by ClientTestScore and CRM queries (PostgreSQL-safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_testscore')) {
            return;
        }

        Schema::table('client_testscore', function (Blueprint $table) {
            if (! Schema::hasColumn('client_testscore', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_testscore', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_testscore', 'test_type')) {
                $table->string('test_type', 50)->nullable()->index();
            }
            if (! Schema::hasColumn('client_testscore', 'listening')) {
                $table->string('listening', 32)->nullable();
            }
            if (! Schema::hasColumn('client_testscore', 'reading')) {
                $table->string('reading', 32)->nullable();
            }
            if (! Schema::hasColumn('client_testscore', 'writing')) {
                $table->string('writing', 32)->nullable();
            }
            if (! Schema::hasColumn('client_testscore', 'speaking')) {
                $table->string('speaking', 32)->nullable();
            }
            if (! Schema::hasColumn('client_testscore', 'overall_score')) {
                $table->string('overall_score', 32)->nullable();
            }
            if (! Schema::hasColumn('client_testscore', 'proficiency_level')) {
                $table->string('proficiency_level', 191)->nullable();
            }
            if (! Schema::hasColumn('client_testscore', 'proficiency_points')) {
                $table->integer('proficiency_points')->nullable();
            }
            if (! Schema::hasColumn('client_testscore', 'test_date')) {
                $table->date('test_date')->nullable()->index();
            }
            if (! Schema::hasColumn('client_testscore', 'relevant_test')) {
                $table->boolean('relevant_test')->default(false);
            }
            if (! Schema::hasColumn('client_testscore', 'test_reference_no')) {
                $table->string('test_reference_no', 191)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_testscore')) {
            return;
        }

        $cols = array_values(array_filter([
            'client_id', 'admin_id', 'test_type', 'listening', 'reading', 'writing', 'speaking',
            'proficiency_level', 'proficiency_points', 'test_date', 'relevant_test', 'test_reference_no',
        ], fn ($c) => Schema::hasColumn('client_testscore', $c)));

        // Do not drop overall_score here: may have existed on stub before this migration.
        if ($cols === []) {
            return;
        }

        Schema::table('client_testscore', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
