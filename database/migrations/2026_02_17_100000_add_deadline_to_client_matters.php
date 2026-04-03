<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add deadline column to client_matters.
     * Deadline is optional (nullable). Use "Set Deadline" checkbox + date picker in UI.
     */
    public function up(): void
    {
        Schema::table('client_matters', function (Blueprint $table) {
            $table->date('deadline')->nullable()->after('matter_status')
                ->comment('Optional matter deadline; null when not set');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_matters', function (Blueprint $table) {
            $table->dropColumn('deadline');
        });
    }
};
