<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_fields')) {
            return;
        }

        Schema::create('signature_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained('signers')->nullOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->integer('x_position')->default(0);
            $table->integer('y_position')->default(0);
            $table->double('x_percent')->nullable();
            $table->double('y_percent')->nullable();
            $table->double('width_percent')->nullable();
            $table->double('height_percent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_fields');
    }
};
