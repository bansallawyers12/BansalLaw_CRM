<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove matter-level department / other reference fields (feature retired).
     */
    public function up(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            if (Schema::hasColumn('client_matters', 'department_reference')) {
                $table->dropColumn('department_reference');
            }
            if (Schema::hasColumn('client_matters', 'other_reference')) {
                $table->dropColumn('other_reference');
            }
        });
    }

    public function down(): void
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
};
