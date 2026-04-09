<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub client_addresses (id + timestamps only) breaks selects for address, suburb, country, zip, etc.
 * Align with ClientAddress model and CRM usage (PostgreSQL-safe: no ->after()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_addresses')) {
            return;
        }

        Schema::table('client_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('client_addresses', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_addresses', 'admin_id')) {
                $table->unsignedBigInteger('admin_id')->nullable()->index();
            }
            if (! Schema::hasColumn('client_addresses', 'address')) {
                $table->text('address')->nullable();
            }
            if (! Schema::hasColumn('client_addresses', 'address_line_1')) {
                $table->string('address_line_1', 255)->nullable();
            }
            if (! Schema::hasColumn('client_addresses', 'address_line_2')) {
                $table->string('address_line_2', 255)->nullable();
            }
            if (! Schema::hasColumn('client_addresses', 'suburb')) {
                $table->string('suburb', 100)->nullable()->index();
            }
            if (! Schema::hasColumn('client_addresses', 'state')) {
                $table->string('state', 100)->nullable();
            }
            if (! Schema::hasColumn('client_addresses', 'country')) {
                $table->string('country', 100)->nullable()->index();
            }
            if (! Schema::hasColumn('client_addresses', 'zip')) {
                $table->string('zip', 20)->nullable();
            }
            if (! Schema::hasColumn('client_addresses', 'regional_code')) {
                $table->string('regional_code', 50)->nullable();
            }
            if (! Schema::hasColumn('client_addresses', 'start_date')) {
                $table->date('start_date')->nullable();
            }
            if (! Schema::hasColumn('client_addresses', 'end_date')) {
                $table->date('end_date')->nullable();
            }
            if (! Schema::hasColumn('client_addresses', 'is_current')) {
                $table->boolean('is_current')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_addresses')) {
            return;
        }

        $cols = array_values(array_filter([
            'client_id', 'admin_id', 'address', 'address_line_1', 'address_line_2',
            'suburb', 'state', 'country', 'zip', 'regional_code',
            'start_date', 'end_date', 'is_current',
        ], fn ($c) => Schema::hasColumn('client_addresses', $c)));

        if ($cols === []) {
            return;
        }

        Schema::table('client_addresses', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
