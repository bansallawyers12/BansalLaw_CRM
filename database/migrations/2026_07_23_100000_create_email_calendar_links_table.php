<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_calendar_links')) {
            return;
        }

        Schema::create('email_calendar_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('email_log_id');
            $table->string('calendar_type', 32)->comment('staff_event or court_hearing');
            $table->unsignedBigInteger('calendar_id')->nullable();
            $table->string('event_type', 32)->default('meeting');
            $table->string('event_title', 255);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('source', 32)->default('text_detection');
            $table->string('status', 16)->default('merged')->comment('merged or pending');
            $table->timestamps();

            $table->index('email_log_id');
            $table->index(['calendar_type', 'calendar_id']);
            $table->index('starts_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_calendar_links');
    }
};
