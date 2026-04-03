<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 1. Rename workflow stage "Payment verified" to "Verification: Payment, Service Agreement, Forms"
     * 2. Create client_matter_payment_forms_verifications table for Migration Agent verification records
     */
    public function up(): void
    {
        // Rename the workflow stage
        DB::table('workflow_stages')
            ->whereRaw("LOWER(TRIM(name)) = 'payment verified'")
            ->update(['name' => 'Verification: Payment, Service Agreement, Forms']);

        // Create verification records table
        Schema::create('client_matter_payment_forms_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_matter_id');
            $table->unsignedBigInteger('verified_by')->comment('Migration Agent (staff id) who verified');
            $table->timestamp('verified_at');
            $table->text('note')->nullable()->comment('Optional text from Migration Agent');
            $table->timestamps();

            $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
            $table->index('client_matter_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_matter_payment_forms_verifications');

        DB::table('workflow_stages')
            ->where('name', 'Verification: Payment, Service Agreement, Forms')
            ->update(['name' => 'Payment verified']);
    }
};
