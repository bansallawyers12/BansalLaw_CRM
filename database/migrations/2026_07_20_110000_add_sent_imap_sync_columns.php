<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('emails')) {
            return;
        }

        Schema::table('emails', function (Blueprint $table) {
            if (! Schema::hasColumn('emails', 'last_imap_uid_sent')) {
                $table->unsignedBigInteger('last_imap_uid_sent')->nullable()->after('last_imap_uid');
            }
            if (! Schema::hasColumn('emails', 'sync_sent_enabled')) {
                $table->boolean('sync_sent_enabled')->default(false)->after('sync_enabled');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('emails')) {
            return;
        }

        Schema::table('emails', function (Blueprint $table) {
            foreach (['last_imap_uid_sent', 'sync_sent_enabled'] as $col) {
                if (Schema::hasColumn('emails', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
