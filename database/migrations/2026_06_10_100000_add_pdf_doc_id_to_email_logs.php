<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        if (! Schema::hasColumn('email_logs', 'pdf_doc_id')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('pdf_doc_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        if (Schema::hasColumn('email_logs', 'pdf_doc_id')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->dropColumn('pdf_doc_id');
            });
        }
    }
};
