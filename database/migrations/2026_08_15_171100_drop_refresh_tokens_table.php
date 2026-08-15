<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused refresh_tokens store (client portal + staff API login removed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }

    public function down(): void
    {
        if (Schema::hasTable('refresh_tokens')) {
            return;
        }

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('token', 500)->unique();
            $table->string('device_name')->nullable();
            $table->timestamp('expires_at');
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('admins')->onDelete('cascade');
            $table->index(['user_id', 'is_revoked']);
            $table->index('token');
            $table->index('expires_at');
        });
    }
};
