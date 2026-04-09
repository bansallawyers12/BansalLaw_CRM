<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            if (! Schema::hasColumn('client_matters', 'date_of_incidence')) {
                $table->date('date_of_incidence')->nullable();
            }
            if (! Schema::hasColumn('client_matters', 'incidence_type')) {
                $table->string('incidence_type', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        $cols = array_values(array_filter(
            ['date_of_incidence', 'incidence_type'],
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
