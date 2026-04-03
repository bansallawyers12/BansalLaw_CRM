<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('document_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('document_id');
            $table->unsignedInteger('created_by')->comment('Admin who performed the action');
            $table->string('action_type', 50)->comment('associated, detached, status_changed, etc.');
            $table->text('note')->nullable()->comment('User-provided note or system-generated description');
            $table->json('metadata')->nullable()->comment('Additional data: entity type/id, old values, etc.');
            $table->timestamps();

            // Indexes
            $table->index('document_id');
            $table->index('created_by');
            $table->index('action_type');
            $table->index('created_at');

            // Foreign keys (optional - only if constraints are needed)
            // Note: Commented out to avoid foreign key constraint issues with legacy tables
            // $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            // $table->foreign('created_by')->references('id')->on('admins')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('document_notes');
    }
};
