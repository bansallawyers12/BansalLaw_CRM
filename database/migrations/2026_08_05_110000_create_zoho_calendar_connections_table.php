<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zoho_calendar_connections')) {
            return;
        }

        Schema::create('zoho_calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->unique();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('zoho_email', 255)->nullable();
            $table->string('accounts_server', 255)->nullable();
            $table->string('api_domain', 255)->nullable();
            $table->string('default_calendar_uid', 128)->nullable();
            $table->string('scopes', 500)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_calendar_connections');
    }
};
