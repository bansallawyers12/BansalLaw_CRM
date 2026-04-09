<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub client_qualifications may only have client_id, country, relevant_qualification, timestamps.
 * Add columns used by ClientQualification and list/detail queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_qualifications')) {
            return;
        }

        Schema::table('client_qualifications', function (Blueprint $table) {
            if (! Schema::hasColumn('client_qualifications', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_qualifications', 'level')) {
                $table->string('level', 191)->nullable();
            }
            if (! Schema::hasColumn('client_qualifications', 'name')) {
                $table->string('name', 500)->nullable();
            }
            if (! Schema::hasColumn('client_qualifications', 'qual_college_name')) {
                $table->string('qual_college_name', 500)->nullable();
            }
            if (! Schema::hasColumn('client_qualifications', 'qual_campus')) {
                $table->string('qual_campus', 255)->nullable();
            }
            if (! Schema::hasColumn('client_qualifications', 'qual_state')) {
                $table->string('qual_state', 100)->nullable();
            }
            if (! Schema::hasColumn('client_qualifications', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            if (! Schema::hasColumn('client_qualifications', 'finish_date')) {
                $table->date('finish_date')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_qualifications')) {
            return;
        }

        $cols = array_values(array_filter([
            'admin_id', 'level', 'name', 'qual_college_name', 'qual_campus', 'qual_state',
            'start_date', 'finish_date',
        ], fn ($c) => Schema::hasColumn('client_qualifications', $c)));

        if ($cols === []) {
            return;
        }

        Schema::table('client_qualifications', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
