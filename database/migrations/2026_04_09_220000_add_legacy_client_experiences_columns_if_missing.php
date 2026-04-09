<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub client_experiences may only have client_id, job_country, job_type, timestamps.
 * Add columns used by ClientExperience and CRM queries (PostgreSQL-safe).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_experiences')) {
            return;
        }

        Schema::table('client_experiences', function (Blueprint $table) {
            if (! Schema::hasColumn('client_experiences', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_experiences', 'job_title')) {
                $table->string('job_title', 500)->nullable();
            }
            if (! Schema::hasColumn('client_experiences', 'job_code')) {
                $table->string('job_code', 100)->nullable();
            }
            if (! Schema::hasColumn('client_experiences', 'job_start_date')) {
                $table->date('job_start_date')->nullable();
            }
            if (! Schema::hasColumn('client_experiences', 'job_finish_date')) {
                $table->date('job_finish_date')->nullable()->index();
            }
            if (! Schema::hasColumn('client_experiences', 'relevant_experience')) {
                $table->boolean('relevant_experience')->default(false);
            }
            if (! Schema::hasColumn('client_experiences', 'job_emp_name')) {
                $table->string('job_emp_name', 500)->nullable();
            }
            if (! Schema::hasColumn('client_experiences', 'job_state')) {
                $table->string('job_state', 100)->nullable();
            }
            if (! Schema::hasColumn('client_experiences', 'fte_multiplier')) {
                $table->decimal('fte_multiplier', 3, 2)->default(1.00);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_experiences')) {
            return;
        }

        $cols = array_values(array_filter([
            'admin_id', 'job_title', 'job_code', 'job_start_date', 'job_finish_date',
            'relevant_experience', 'job_emp_name', 'job_state', 'fte_multiplier',
        ], fn ($c) => Schema::hasColumn('client_experiences', $c)));

        if ($cols === []) {
            return;
        }

        Schema::table('client_experiences', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
