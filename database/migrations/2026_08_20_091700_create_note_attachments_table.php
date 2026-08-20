<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('note_attachments')) {
            return;
        }

        Schema::create('note_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('note_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->string('original_name', 512);
            $table->string('stored_path', 1024);
            $table->string('mime_type', 191)->nullable();
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_attachments');
    }
};
