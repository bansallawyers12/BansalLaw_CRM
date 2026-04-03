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
        Schema::create('appointment_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('sync_type', ['polling', 'manual', 'backfill'])->default('polling');
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            
            // Statistics
            $table->integer('appointments_fetched')->default(0);
            $table->integer('appointments_new')->default(0);
            $table->integer('appointments_updated')->default(0);
            $table->integer('appointments_skipped')->default(0);
            $table->integer('appointments_failed')->default(0);
            
            // Details
            $table->text('error_message')->nullable();
            $table->json('sync_details')->nullable()->comment('API response, errors, etc.');
            
            $table->timestamps();
            
            $table->index('sync_type');
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_sync_logs');
    }
};

