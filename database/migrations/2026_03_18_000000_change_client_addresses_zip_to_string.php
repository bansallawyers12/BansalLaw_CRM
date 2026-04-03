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
     *
     * Fresh-install stub tables may omit zip entirely; add it as string in that case.
     */
    public function up(): void
    {
        if (!Schema::hasTable('client_addresses')) {
            return;
        }

        if (!Schema::hasColumn('client_addresses', 'zip')) {
            Schema::table('client_addresses', function (Blueprint $table) {
                $table->string('zip', 20)->nullable();
            });

            return;
        }

        $dataType = $this->zipColumnDataType();
        $integerTypes = ['integer', 'bigint', 'smallint', 'int'];

        if (DB::getDriverName() === 'pgsql') {
            if (!in_array($dataType, $integerTypes, true)) {
                return;
            }
            DB::statement('ALTER TABLE client_addresses ALTER COLUMN zip TYPE VARCHAR(20) USING zip::text');
        } else {
            if (!in_array($dataType, $integerTypes, true)) {
                return;
            }
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
        if (!Schema::hasTable('client_addresses') || !Schema::hasColumn('client_addresses', 'zip')) {
            return;
        }

        $dataType = $this->zipColumnDataType();
        $integerTypes = ['integer', 'bigint', 'smallint', 'int'];

        if (in_array($dataType, $integerTypes, true)) {
            return;
        }

        $hasNonEmptyZip = DB::table('client_addresses')
            ->whereNotNull('zip')
            ->where('zip', '!=', '')
            ->exists();

        if (!$hasNonEmptyZip) {
            Schema::table('client_addresses', function (Blueprint $table) {
                $table->dropColumn('zip');
            });

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

    private function zipColumnDataType(): ?string
    {
        if (DB::getDriverName() === 'pgsql') {
            $row = DB::selectOne(
                "SELECT data_type FROM information_schema.columns
                 WHERE table_schema = current_schema()
                 AND table_name = 'client_addresses'
                 AND column_name = 'zip'"
            );

            return isset($row->data_type) ? strtolower((string) $row->data_type) : null;
        }

        if (DB::getDriverName() === 'mysql') {
            $row = DB::selectOne(
                "SELECT DATA_TYPE AS data_type FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'client_addresses'
                 AND COLUMN_NAME = 'zip'"
            );

            return isset($row->data_type) ? strtolower((string) $row->data_type) : null;
        }

        return null;
    }
};
