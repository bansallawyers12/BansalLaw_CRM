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

        if (Schema::hasColumn('client_matters', 'case_detail')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->text('case_detail')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_matters') || ! Schema::hasColumn('client_matters', 'case_detail')) {
            return;
        }

        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn('case_detail');
        });
    }
};
