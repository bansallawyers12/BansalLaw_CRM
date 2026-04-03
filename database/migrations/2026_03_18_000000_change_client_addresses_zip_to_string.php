<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change client_addresses.zip from integer to string so overseas postcodes
     * (e.g. UK, Canada) with letters can be stored.
     */
    public function up(): void
    {
        if (!Schema::hasTable('client_addresses')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE client_addresses ALTER COLUMN zip TYPE VARCHAR(20) USING zip::text');
        } else {
            Schema::table('client_addresses', function (Blueprint $table) {
                $table->string('zip', 20)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('client_addresses')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            // Only numeric zip values convert back to integer; non-numeric become NULL
            DB::statement("ALTER TABLE client_addresses ALTER COLUMN zip TYPE INTEGER USING (CASE WHEN zip ~ '^\\s*\\d+\\s*$' THEN trim(zip)::integer ELSE NULL END)");
        } else {
            Schema::table('client_addresses', function (Blueprint $table) {
                $table->integer('zip')->nullable()->change();
            });
        }
    }
};
