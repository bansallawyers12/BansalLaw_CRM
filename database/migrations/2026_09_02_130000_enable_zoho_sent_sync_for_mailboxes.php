<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('emails') || ! Schema::hasColumn('emails', 'sync_sent_enabled')) {
            return;
        }

        // Existing syncable mailboxes must pull Zoho Sent so the Mail → Sent tab can list them.
        DB::table('emails')
            ->where('sync_enabled', 1)
            ->where(function ($q) {
                $q->whereNull('sync_sent_enabled')->orWhere('sync_sent_enabled', 0);
            })
            ->update(['sync_sent_enabled' => 1]);
    }

    public function down(): void
    {
        // Intentionally left blank — do not disable Sent sync on rollback.
    }
};
