<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_testscore')) {
            return;
        }

        $cols = array_values(array_filter(
            ['proficiency_level', 'proficiency_points'],
            fn (string $c) => Schema::hasColumn('client_testscore', $c)
        ));

        if ($cols === []) {
            return;
        }

        Schema::table('client_testscore', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }

    public function down(): void
    {
        // Intentionally empty: skilled-migration proficiency points are retired.
    }
};
