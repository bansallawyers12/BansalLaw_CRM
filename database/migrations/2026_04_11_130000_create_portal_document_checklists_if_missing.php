<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL / stub installs may lack portal_document_checklists (renamed from document_checklists in 2026_02_23).
 * Used by DocumentChecklist model, client detail modals, and client portal APIs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_document_checklists')) {
            return;
        }

        Schema::create('portal_document_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->tinyInteger('doc_type')->default(1)->index();
            $table->tinyInteger('status')->default(1)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('portal_document_checklists')) {
            return;
        }

        Schema::drop('portal_document_checklists');
    }
};
