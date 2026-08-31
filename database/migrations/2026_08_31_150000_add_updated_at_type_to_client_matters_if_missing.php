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

        if (Schema::hasColumn('client_matters', 'updated_at_type')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->string('updated_at_type', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_matters')) {
            return;
        }

        if (! Schema::hasColumn('client_matters', 'updated_at_type')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn('updated_at_type');
        });
    }
};
