<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('communication_check_runs')) {
            return;
        }

        Schema::create('communication_check_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('batch_token', 36)->unique();
            $table->unsignedSmallInteger('lookback_days')->default(30);
            $table->unsignedSmallInteger('file_count')->default(0);
            $table->json('extracted')->nullable();
            $table->json('results')->nullable();
            $table->json('queried')->nullable();
            $table->string('storage_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_check_runs');
    }
};
