<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zoho_calendar_staff_maps')) {
            Schema::create('zoho_calendar_staff_maps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->string('zoho_email', 255)->nullable();
                $table->string('zoho_calendar_uid', 128)->nullable();
                $table->boolean('sync_enabled')->default(false);
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index('sync_enabled');
            });
        }

        if (! Schema::hasTable('zoho_calendar_event_links')) {
            Schema::create('zoho_calendar_event_links', function (Blueprint $table) {
                $table->id();
                $table->string('local_type', 40);
                $table->unsignedBigInteger('local_id');
                $table->unsignedBigInteger('staff_id')->nullable();
                $table->string('zoho_event_uid', 191)->nullable();
                $table->string('zoho_calendar_uid', 128)->nullable();
                $table->unsignedInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->string('file_ref', 100)->nullable();
                $table->string('matter_ref', 100)->nullable();
                $table->string('sync_status', 32)->default('pending');
                $table->string('direction', 32)->default('crm_to_zoho');
                $table->string('etag', 191)->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['local_type', 'local_id']);
                $table->index(['zoho_event_uid']);
                $table->index(['staff_id', 'sync_status']);
                $table->index('client_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_calendar_event_links');
        Schema::dropIfExists('zoho_calendar_staff_maps');
    }
};
