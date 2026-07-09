<?php

/**
 * One-time data migration: update stored FA4/5 icon class strings to FA6.
 *
 * Prefer the artisan command (idempotent, uses FontAwesomeHelper):
 *   php artisan fontawesome:migrate-db-icons
 *   php artisan fontawesome:migrate-db-icons --dry-run
 *
 * This migration is safe to run on environments that already have FA6 seed
 * values — rows that do not need changes are left alone.
 */

use App\Helpers\FontAwesomeHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_labels') || ! Schema::hasColumn('email_labels', 'icon')) {
            return;
        }

        $rows = DB::table('email_labels')
            ->whereNotNull('icon')
            ->where('icon', '!=', '')
            ->get(['id', 'icon']);

        foreach ($rows as $row) {
            $migrated = FontAwesomeHelper::migrateClasses((string) $row->icon);
            if ($migrated === $row->icon) {
                continue;
            }

            DB::table('email_labels')->where('id', $row->id)->update([
                'icon' => $migrated,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible: FA6 names are the canonical store going forward.
    }
};
