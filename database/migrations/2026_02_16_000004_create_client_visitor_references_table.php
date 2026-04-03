<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates client_visitor_references for Visitor Visa (600) sheet.
     */
    public function up(): void
    {
        Schema::create('client_visitor_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('client_matter_id')->index();

            $table->text('current_status')->nullable();
            $table->string('payment_display_note', 100)->nullable();
            $table->string('institute_override', 255)->nullable();
            $table->string('visa_category_override', 50)->nullable();
            $table->text('comments')->nullable();
            $table->date('checklist_sent_at')->nullable();

            // Audit (staff who created/updated)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('staff')->onDelete('set null');

            $table->unique(['client_id', 'client_matter_id'], 'client_visitor_references_client_matter_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_visitor_references');
    }
};
