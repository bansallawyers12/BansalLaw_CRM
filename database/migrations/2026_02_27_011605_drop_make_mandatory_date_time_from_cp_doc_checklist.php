<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = Schema::hasTable('cp_doc_checklist')
            ? 'cp_doc_checklist'
            : (Schema::hasTable('application_document_lists') ? 'application_document_lists' : null);

        if ($tableName) {
            $cols = array_filter(
                ['make_mandatory', 'date', 'time'],
                fn (string $c) => Schema::hasColumn($tableName, $c)
            );
            if ($cols !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($cols) {
                    $table->dropColumn($cols);
                });
            }
        }
    }

    public function down(): void
    {
        $tableName = Schema::hasTable('cp_doc_checklist')
            ? 'cp_doc_checklist'
            : (Schema::hasTable('application_document_lists') ? 'application_document_lists' : null);

        if ($tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'make_mandatory')) {
                    $table->string('make_mandatory')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'date')) {
                    $table->date('date')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'time')) {
                    $table->time('time')->nullable();
                }
            });
        }
    }
};
