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
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['make_mandatory', 'date', 'time']);
            });
        }
    }

    public function down(): void
    {
        $tableName = Schema::hasTable('cp_doc_checklist')
            ? 'cp_doc_checklist'
            : (Schema::hasTable('application_document_lists') ? 'application_document_lists' : null);

        if ($tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('make_mandatory')->nullable();
                $table->date('date')->nullable();
                $table->time('time')->nullable();
            });
        }
    }
};
