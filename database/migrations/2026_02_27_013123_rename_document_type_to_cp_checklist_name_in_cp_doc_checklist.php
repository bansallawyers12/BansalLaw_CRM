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

        if ($tableName && Schema::hasColumn($tableName, 'document_type')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('document_type', 'cp_checklist_name');
            });
        }
    }

    public function down(): void
    {
        $tableName = Schema::hasTable('cp_doc_checklist')
            ? 'cp_doc_checklist'
            : (Schema::hasTable('application_document_lists') ? 'application_document_lists' : null);

        if ($tableName && Schema::hasColumn($tableName, 'cp_checklist_name')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('cp_checklist_name', 'document_type');
            });
        }
    }
};
