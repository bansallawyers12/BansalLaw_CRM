<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('nomination_document_types');
    }

    public function down(): void
    {
        // Intentionally irreversible — feature removed.
        throw new \RuntimeException('Cannot reverse drop of nomination_document_types.');
    }
};
