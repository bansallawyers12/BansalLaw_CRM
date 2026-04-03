<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates employer_sponsored_matter_reminders for Employer Sponsored Visa Sheet Checklist tab (email/sms/phone reminder audit).
     */
    public function up(): void
    {
        Schema::create('employer_sponsored_matter_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_matter_id')->index();
            $table->string('type', 20)->comment('email, sms, or phone');
            $table->timestamp('reminded_at');
            $table->unsignedBigInteger('reminded_by')->nullable()->comment('Staff who sent the reminder');
            $table->timestamps();

            $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
            $table->foreign('reminded_by')->references('id')->on('staff')->onDelete('set null');

            $table->index(['client_matter_id', 'type'], 'emp_sponsored_reminders_matter_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employer_sponsored_matter_reminders');
    }
};
