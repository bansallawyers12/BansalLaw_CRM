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
        if (Schema::hasTable('system_breakdown_logs')) {
            return;
        }

        Schema::create('system_breakdown_logs', function (Blueprint $table) {
            $table->id();
            $table->string('error_hash', 64)->index();
            $table->string('exception_class', 255)->nullable()->index();
            $table->text('message');
            $table->text('file')->nullable();
            $table->integer('line')->nullable();
            $table->text('url')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('status_code', 10)->nullable()->default('500');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 191)->nullable();
            $table->string('user_email', 191)->nullable()->index();
            $table->string('user_role', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('request_headers')->nullable();
            $table->mediumText('stack_trace')->nullable();
            $table->integer('occurrence_count')->default(1);
            $table->string('status', 20)->default('open')->index(); // open, investigating, resolved, ignored
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('first_seen_at')->nullable()->useCurrent();
            $table->timestamp('last_seen_at')->nullable()->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_breakdown_logs');
    }
};
