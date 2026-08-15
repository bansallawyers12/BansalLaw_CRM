<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove unused payment/forms verification audit table.
 * Workflow stage advances no longer require Legal Practitioner confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('client_matter_payment_forms_verifications');
    }

    public function down(): void
    {
        if (Schema::hasTable('client_matter_payment_forms_verifications')) {
            return;
        }

        Schema::create('client_matter_payment_forms_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_matter_id');
            $table->unsignedBigInteger('verified_by')->comment('Staff id who verified');
            $table->timestamp('verified_at');
            $table->text('note')->nullable()->comment('Optional verification note');
            $table->timestamps();

            $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
            $table->index('client_matter_id');
        });
    }
};
