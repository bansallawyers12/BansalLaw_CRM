<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3: Drop legacy BansalCRM lead columns from admins table.
     * See docs/ADMINS_TABLE_COLUMNS.md Implementation Plan.
     */
    public function up(): void
    {
        $columnsToDrop = [
            'assignee',
            'lead_quality',
            'service',
            'comments_note',
            'lead_id',
            'relevant_work_exp_aus',
            'naati_py',
            'nomi_occupation',
            'skill_assessment',
            'high_quali_aus',
            'high_quali_overseas',
            'relevant_work_exp_over',
            'married_partner',
        ];

        $existing = array_filter($columnsToDrop, fn (string $c) => Schema::hasColumn('admins', $c));
        if (!empty($existing)) {
            Schema::table('admins', function (Blueprint $table) use ($existing) {
                $table->dropColumn(array_values($existing));
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $stringCols = ['assignee', 'lead_quality', 'service', 'lead_id', 'nomi_occupation', 'skill_assessment', 'high_quali_aus', 'high_quali_overseas', 'relevant_work_exp_over', 'married_partner'];
        $textCols = ['comments_note', 'relevant_work_exp_aus', 'naati_py'];

        Schema::table('admins', function (Blueprint $table) use ($stringCols, $textCols) {
            foreach ($stringCols as $col) {
                if (!Schema::hasColumn('admins', $col)) {
                    $table->string($col)->nullable();
                }
            }
            foreach ($textCols as $col) {
                if (!Schema::hasColumn('admins', $col)) {
                    $table->text($col)->nullable();
                }
            }
        });
    }
};
