<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matter-level upload checklist rows (model: UploadChecklist). Renamed from upload_checklists in 2026_02_23 when base table was missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('matter_checklists')) {
            return;
        }

        Schema::create('matter_checklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('matter_id')->nullable()->index();
            $table->string('name', 255)->nullable();
            $table->string('file', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('matter_checklists')) {
            return;
        }

        Schema::drop('matter_checklists');
    }
};
