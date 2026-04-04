<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Historical: added `anzsco_occupation_id` on `client_eoi_references` (FK to `anzsco_occupations`).
     * The column and reference table are removed by `2026_04_04_000001_remove_anzsco_occupation_system`.
     * Keep this migration file for existing environments' migration history.
     */
    public function up(): void
    {
        Schema::table('client_eoi_references', function (Blueprint $table) {
            if (!Schema::hasColumn('client_eoi_references', 'anzsco_occupation_id')) {
                $table->unsignedBigInteger('anzsco_occupation_id')->nullable()->after('EOI_occupation')
                    ->comment('FK to anzsco_occupations for occupation autocomplete validation');
                $table->foreign('anzsco_occupation_id')
                    ->references('id')
                    ->on('anzsco_occupations')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_eoi_references', function (Blueprint $table) {
            if (Schema::hasColumn('client_eoi_references', 'anzsco_occupation_id')) {
                $table->dropForeign(['anzsco_occupation_id']);
                $table->dropColumn('anzsco_occupation_id');
            }
        });
    }
};
