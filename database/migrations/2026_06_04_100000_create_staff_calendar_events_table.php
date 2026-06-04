<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_calendar_events')) {
            return;
        }

        Schema::create('staff_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('event_type', 32)->default('meeting');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('calendar_type', 32)->nullable()->comment('ajay, kunal, or null for both');
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedBigInteger('client_matter_id')->nullable();
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->timestamps();

            $table->index('starts_at');
            $table->index('calendar_type');
            $table->index('client_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_calendar_events');
    }
};
