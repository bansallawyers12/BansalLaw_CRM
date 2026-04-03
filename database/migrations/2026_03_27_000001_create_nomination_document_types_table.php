<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomination_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('client_matter_id')->nullable();
            $table->timestamps();

            $table->index(['client_id']);
            $table->index(['client_id', 'client_matter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomination_document_types');
    }
};
