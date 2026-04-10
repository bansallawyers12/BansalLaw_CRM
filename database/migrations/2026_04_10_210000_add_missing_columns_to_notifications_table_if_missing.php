<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy / stub PostgreSQL schema only had receiver_id, notification_type, receiver_status, timestamps.
 * Application code (Notification model, ClientsController::storePersonalAction, broadcasts, etc.)
 * expects sender_id, module_id, url, message, seen, sender_status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->index();
            }
            if (! Schema::hasColumn('notifications', 'module_id')) {
                $table->unsignedBigInteger('module_id')->nullable()->index();
            }
            if (! Schema::hasColumn('notifications', 'url')) {
                $table->text('url')->nullable();
            }
            if (! Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable();
            }
            if (! Schema::hasColumn('notifications', 'seen')) {
                $table->unsignedTinyInteger('seen')->default(0);
            }
            if (! Schema::hasColumn('notifications', 'sender_status')) {
                $table->unsignedTinyInteger('sender_status')->nullable()->default(1);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $cols = array_values(array_filter(
            ['sender_id', 'module_id', 'url', 'message', 'seen', 'sender_status'],
            fn ($c) => Schema::hasColumn('notifications', $c)
        ));
        if ($cols === []) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) use ($cols) {
            $table->dropColumn($cols);
        });
    }
};
