<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'doc_type')) {
            DB::table('documents')->where('doc_type', 'nomination')->delete();
        }

        Schema::dropIfExists('nomination_document_types');
    }

    public function down(): void
    {
        // Intentionally irreversible — feature removed.
        throw new \RuntimeException('Cannot reverse drop of nomination_document_types.');
    }
};
