<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('emails')) {
            return;
        }

        $updates = [];
        if (Schema::hasColumn('emails', 'sync_enabled')) {
            $updates['sync_enabled'] = 0;
        }
        if (Schema::hasColumn('emails', 'sync_sent_enabled')) {
            $updates['sync_sent_enabled'] = 0;
        }

        if ($updates === []) {
            return;
        }

        DB::table('emails')
            ->whereRaw('LOWER(email) = ?', ['michaelsaleh.bi@outlook.com'])
            ->update($updates);
    }

    public function down(): void
    {
        // Intentionally left blank — do not re-enable sync automatically.
    }
};
