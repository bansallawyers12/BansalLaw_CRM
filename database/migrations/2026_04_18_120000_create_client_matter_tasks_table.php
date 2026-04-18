<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_matter_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_matter_id')->index();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('title', 500);
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('client_matter_id')->references('id')->on('client_matters')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_matter_tasks');
    }
};
