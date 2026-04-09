<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub client_visa_countries (id + timestamps) omits visa columns used across CRM / client portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_visa_countries')) {
            return;
        }

        Schema::table('client_visa_countries', function (Blueprint $table) {
            if (! Schema::hasColumn('client_visa_countries', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_visa_countries', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_visa_countries', 'visa_type')) {
                $table->unsignedBigInteger('visa_type')->nullable()->index()->comment('matters.id');
            }
            if (! Schema::hasColumn('client_visa_countries', 'visa_description')) {
                $table->text('visa_description')->nullable();
            }
            if (! Schema::hasColumn('client_visa_countries', 'visa_expiry_date')) {
                $table->date('visa_expiry_date')->nullable()->index();
            }
            if (! Schema::hasColumn('client_visa_countries', 'visa_grant_date')) {
                $table->date('visa_grant_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_visa_countries')) {
            return;
        }

        $cols = array_values(array_filter([
            'client_id', 'admin_id', 'visa_type', 'visa_description', 'visa_expiry_date', 'visa_grant_date',
        ], fn ($c) => Schema::hasColumn('client_visa_countries', $c)));

        if ($cols === []) {
            return;
        }

        Schema::table('client_visa_countries', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
