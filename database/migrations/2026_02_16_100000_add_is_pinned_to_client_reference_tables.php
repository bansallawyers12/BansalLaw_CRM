<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds is_pinned column to all client reference tables for sheet pinning feature.
     */
    public function up(): void
    {
        $tables = [
            'client_tr_references',
            'client_student_references',
            'client_visitor_references',
            'client_pr_references',
            'client_employer_sponsored_references',
            'client_art_references',
            'client_eoi_references',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'is_pinned')) {
                $afterCol = $tableName === 'client_eoi_references' ? 'updated_by' : 'checklist_sent_at';
                Schema::table($tableName, function (Blueprint $table) use ($tableName, $afterCol) {
                    if (Schema::hasColumn($tableName, $afterCol)) {
                        $table->boolean('is_pinned')->default(false)->after($afterCol)->index();
                    } else {
                        $table->boolean('is_pinned')->default(false)->index();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'client_tr_references',
            'client_student_references',
            'client_visitor_references',
            'client_pr_references',
            'client_employer_sponsored_references',
            'client_art_references',
            'client_eoi_references',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'is_pinned')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('is_pinned');
                });
            }
        }
    }
};
