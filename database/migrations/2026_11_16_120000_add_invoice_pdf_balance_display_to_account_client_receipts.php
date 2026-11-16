<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_client_receipts')) {
            return;
        }

        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('account_client_receipts', 'invoice_pdf_balance_display')) {
                $table->string('invoice_pdf_balance_display', 16)->nullable()->after('pdf_document_id');
            }
            if (! Schema::hasColumn('account_client_receipts', 'invoice_pdf_template_version')) {
                $table->unsignedSmallInteger('invoice_pdf_template_version')->nullable()->after('invoice_pdf_balance_display');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_client_receipts')) {
            return;
        }

        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('account_client_receipts', 'invoice_pdf_template_version')) {
                $table->dropColumn('invoice_pdf_template_version');
            }
            if (Schema::hasColumn('account_client_receipts', 'invoice_pdf_balance_display')) {
                $table->dropColumn('invoice_pdf_balance_display');
            }
        });
    }
};