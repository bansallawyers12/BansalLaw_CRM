<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('client_legal_forms', 'source_document_id')) {
            return;
        }

        Schema::table('client_legal_forms', function (Blueprint $table) {
            $table->unsignedBigInteger('source_document_id')->nullable()->unique()->after('created_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('client_legal_forms', 'source_document_id')) {
            return;
        }

        Schema::table('client_legal_forms', function (Blueprint $table) {
            $table->dropUnique(['source_document_id']);
            $table->dropColumn('source_document_id');
        });
    }
};
