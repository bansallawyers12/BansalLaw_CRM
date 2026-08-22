<?php
/**
 * Quick script to mark migrations as run
 * Usage: php mark_migrations.php migration1 migration2 migration3
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$migrations = array_slice($argv, 1);

if (empty($migrations)) {
    echo "Usage: php mark_migrations.php migration1 migration2 ...\n";
    echo "Example: php mark_migrations.php 2025_10_04_202019_create_email_verifications_table\n";
    exit(1);
}

$existingMigrations = DB::table('migrations')->pluck('migration')->toArray();
$maxBatch = DB::table('migrations')->max('batch') ?? 0;
$nextBatch = $maxBatch + 1;

$toInsert = array_filter($migrations, fn($m) => !in_array($m, $existingMigrations));

if (empty($toInsert)) {
    echo "All migrations are already recorded.\n";
    exit(0);
}

foreach ($toInsert as $migration) {
    DB::table('migrations')->insert([
        'migration' => $migration,
        'batch' => $nextBatch
    ]);
    echo "✓ Marked as run: $migration\n";
}

echo "\nDone! Marked " . count($toInsert) . " migration(s) as run in batch $nextBatch\n";
