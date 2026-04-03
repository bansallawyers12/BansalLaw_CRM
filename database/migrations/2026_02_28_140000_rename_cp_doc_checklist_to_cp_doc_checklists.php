<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cp_doc_checklist') && !Schema::hasTable('cp_doc_checklists')) {
            Schema::rename('cp_doc_checklist', 'cp_doc_checklists');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cp_doc_checklists') && !Schema::hasTable('cp_doc_checklist')) {
            Schema::rename('cp_doc_checklists', 'cp_doc_checklist');
        }
    }
};
