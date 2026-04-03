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
        Schema::create('appointment_consultants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->enum('calendar_type', ['paid', 'jrp', 'education', 'tourist', 'adelaide']);
            $table->enum('location', ['melbourne', 'adelaide'])->default('melbourne');
            $table->json('specializations')->nullable()->comment('Array of noe_ids this consultant handles');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Laravel 12: Better index naming
            $table->index('calendar_type', 'idx_calendar_type');
            $table->index('is_active', 'idx_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_consultants');
    }
};

