<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renames checklist tables for better identification:
     * - document_checklists → portal_document_checklists (Client Portal document types)
     * - upload_checklists → matter_checklists (Matter-specific checklists with file attachments)
     */
    public function up(): void
    {
        if (Schema::hasTable('document_checklists') && !Schema::hasTable('portal_document_checklists')) {
            Schema::rename('document_checklists', 'portal_document_checklists');
        }
        if (Schema::hasTable('upload_checklists') && !Schema::hasTable('matter_checklists')) {
            Schema::rename('upload_checklists', 'matter_checklists');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('portal_document_checklists') && !Schema::hasTable('document_checklists')) {
            Schema::rename('portal_document_checklists', 'document_checklists');
        }
        if (Schema::hasTable('matter_checklists') && !Schema::hasTable('upload_checklists')) {
            Schema::rename('matter_checklists', 'upload_checklists');
        }
    }
};
