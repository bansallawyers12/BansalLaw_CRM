<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Office-specific settings table removed:
     * - date_format and time_format were hardcoded in code; DB never used
     * - Other fields had minimal use; functionality deprecated
     */
    public function up(): void
    {
        Schema::dropIfExists('settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->integer('office_id');
            $table->string('date_format')->nullable();
            $table->string('time_format')->nullable();
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
    }
};
