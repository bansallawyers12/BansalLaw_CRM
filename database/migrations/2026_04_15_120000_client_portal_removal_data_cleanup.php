<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notes')) {
            DB::table('notes')
                ->where('task_group', 'Client Portal')
                ->update(['task_group' => 'Query']);
        }

        Schema::dropIfExists('clientportal_details_audit');

        if (Schema::hasTable('portal_document_checklists') && ! Schema::hasTable('document_checklists')) {
            Schema::rename('portal_document_checklists', 'document_checklists');
        }
    }

    /**
     * Only reverses the checklist table rename. Does not restore clientportal_details_audit
     * or revert notes.task_group (Client Portal → Query).
     */
    public function down(): void
    {
        if (Schema::hasTable('document_checklists') && ! Schema::hasTable('portal_document_checklists')) {
            Schema::rename('document_checklists', 'portal_document_checklists');
        }
    }
};
