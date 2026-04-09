<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub client_occupations (id + timestamps) omits columns used by ClientOccupation and CRM queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_occupations')) {
            return;
        }

        Schema::table('client_occupations', function (Blueprint $table) {
            if (! Schema::hasColumn('client_occupations', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_occupations', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_occupations', 'skill_assessment')) {
                $table->string('skill_assessment', 255)->nullable();
            }
            if (! Schema::hasColumn('client_occupations', 'nomi_occupation')) {
                $table->string('nomi_occupation', 500)->nullable();
            }
            if (! Schema::hasColumn('client_occupations', 'occupation_code')) {
                $table->string('occupation_code', 100)->nullable();
            }
            if (! Schema::hasColumn('client_occupations', 'list')) {
                $table->string('list', 100)->nullable();
            }
            if (! Schema::hasColumn('client_occupations', 'visa_subclass')) {
                $table->string('visa_subclass', 100)->nullable();
            }
            if (! Schema::hasColumn('client_occupations', 'dates')) {
                $table->date('dates')->nullable();
            }
            if (! Schema::hasColumn('client_occupations', 'expiry_dates')) {
                $table->date('expiry_dates')->nullable();
            }
            if (! Schema::hasColumn('client_occupations', 'relevant_occupation')) {
                $table->boolean('relevant_occupation')->default(false);
            }
            if (! Schema::hasColumn('client_occupations', 'occ_reference_no')) {
                $table->string('occ_reference_no', 191)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_occupations')) {
            return;
        }

        $cols = array_values(array_filter([
            'client_id', 'admin_id', 'skill_assessment', 'nomi_occupation', 'occupation_code',
            'list', 'visa_subclass', 'dates', 'expiry_dates', 'relevant_occupation', 'occ_reference_no',
        ], fn ($c) => Schema::hasColumn('client_occupations', $c)));

        if ($cols === []) {
            return;
        }

        Schema::table('client_occupations', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
