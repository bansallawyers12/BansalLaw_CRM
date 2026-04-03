<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('account_client_receipts', 'pdf_document_id')) {
                $table->unsignedBigInteger('pdf_document_id')->nullable()->after('uploaded_doc_id');
                $table->index('pdf_document_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('account_client_receipts', 'pdf_document_id')) {
                $table->dropIndex(['pdf_document_id']);
                $table->dropColumn('pdf_document_id');
            }
        });
    }
};

