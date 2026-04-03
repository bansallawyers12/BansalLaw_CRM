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
        Schema::create('client_art_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('client_matter_id')->index();

            // Core ART fields (from ART Submission and Hearing Files sheet)
            $table->string('other_reference')->nullable()->comment('e.g. CHAUIS.24.0276');
            $table->date('submission_last_date')->nullable();
            $table->string('status_of_file', 50)->default('submission_pending')->comment('submission_pending, submission_done, hearing_invitation_sent, waiting_for_hearing, hearing, decided, withdrawn');
            $table->string('hearing_time')->nullable()->comment('e.g. 10:30 am (NSW time)');
            $table->string('member_name')->nullable()->comment('Tribunal member');
            $table->string('outcome')->nullable();
            $table->text('comments')->nullable();

            // Workflow (optional, mirror EOI)
            $table->boolean('staff_verified')->default(false);
            $table->timestamp('verification_date')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('client_confirmation_status', 50)->nullable();
            $table->timestamp('client_last_confirmation')->nullable();
            $table->text('client_confirmation_notes')->nullable();
            $table->string('client_confirmation_token')->nullable();
            $table->timestamp('confirmation_email_sent_at')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('admins')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('admins')->onDelete('set null');

            $table->index(['client_id', 'status_of_file'], 'idx_art_client_status');
            $table->index('submission_last_date', 'idx_art_submission_date');
            $table->index('status_of_file', 'idx_art_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_art_references');
    }
};
