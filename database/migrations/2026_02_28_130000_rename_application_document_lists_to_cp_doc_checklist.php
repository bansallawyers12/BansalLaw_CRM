<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('application_document_lists')) {
            Schema::rename('application_document_lists', 'cp_doc_checklist');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cp_doc_checklist')) {
            Schema::rename('cp_doc_checklist', 'application_document_lists');
        }
    }
};
