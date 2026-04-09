<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * client_matters.department_reference / other_reference are used on client detail and accounts UI.
 * Stub PostgreSQL schema omitted them; add when missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            if (! Schema::hasColumn('client_matters', 'department_reference')) {
                $table->string('department_reference', 255)->nullable();
            }
            if (! Schema::hasColumn('client_matters', 'other_reference')) {
                $table->string('other_reference', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        $cols = array_values(array_filter(
            ['department_reference', 'other_reference'],
            fn ($c) => Schema::hasColumn('client_matters', $c)
        ));
        if ($cols === []) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
