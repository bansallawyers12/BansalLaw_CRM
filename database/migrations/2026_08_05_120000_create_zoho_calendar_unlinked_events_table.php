<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zoho_calendar_unlinked_events')) {
            return;
        }

        Schema::create('zoho_calendar_unlinked_events', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_event_uid', 191);
            $table->string('zoho_calendar_uid', 128)->nullable();
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('etag', 191)->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('parsed_file_ref', 100)->nullable();
            $table->string('parsed_matter_ref', 100)->nullable();
            $table->string('status', 32)->default('open'); // open | linked | dismissed
            $table->string('linked_local_type', 40)->nullable();
            $table->unsignedBigInteger('linked_local_id')->nullable();
            $table->unsignedBigInteger('resolved_by_staff_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['zoho_event_uid', 'zoho_calendar_uid'], 'zoho_unlinked_event_uid_cal_unique');
            $table->index(['status', 'starts_at']);
            $table->index('parsed_file_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_calendar_unlinked_events');
    }
};
