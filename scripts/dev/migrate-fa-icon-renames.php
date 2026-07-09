<?php

/**
 * One-shot: rename FA5 icon names still present after prefix migration.
 * Safe to re-run (idempotent once names are FA6).
 *
 * Lives in scripts/dev/ — paths are relative to that folder.
 */

$map = [
    // Critical (already mostly done)
    'external-link-alt' => 'up-right-from-square',
    'external-link-square-alt' => 'square-up-right',
    'calendar-alt' => 'calendar-days',
    'file-alt' => 'file-lines',
    'map-marker-alt' => 'location-dot',

    // Common FA5 → FA6 Free renames
    'cloud-upload-alt' => 'cloud-arrow-up',
    'cloud-download-alt' => 'cloud-arrow-down',
    'sign-out-alt' => 'right-from-bracket',
    'sign-in-alt' => 'right-to-bracket',
    'sync-alt' => 'rotate',
    'exchange-alt' => 'right-left',
    'undo-alt' => 'arrow-rotate-left',
    'redo-alt' => 'arrow-rotate-right',
    'mobile-alt' => 'mobile-screen-button',
    'user-alt' => 'user-large',
    'home-alt' => 'house',
    'phone-alt' => 'phone',
    'shield-alt' => 'shield-halved',
    'long-arrow-alt-right' => 'arrow-right-long',
    'long-arrow-alt-left' => 'arrow-left-long',
    'long-arrow-alt-up' => 'arrow-up-long',
    'long-arrow-alt-down' => 'arrow-down-long',
    'expand-arrows-alt' => 'up-right-and-down-left-from-center',
    'compress-arrows-alt' => 'down-left-and-up-right-to-center',
    'level-up-alt' => 'turn-up',
    'level-down-alt' => 'turn-down',
    'arrows-alt-h' => 'left-right',
    'arrows-alt-v' => 'up-down',
    'comment-alt' => 'comment',
    'search-plus' => 'magnifying-glass-plus',
    'search-minus' => 'magnifying-glass-minus',

    // Circle / status icons
    'check-circle' => 'circle-check',
    'times-circle' => 'circle-xmark',
    'exclamation-triangle' => 'triangle-exclamation',
    'exclamation-circle' => 'circle-exclamation',
    'info-circle' => 'circle-info',
    'calendar-times' => 'calendar-xmark',
    'question-circle' => 'circle-question',

    // Misc renames
    'edit' => 'pen-to-square',
    'redo' => 'arrow-rotate-right',
    'archive' => 'box-archive',
    'save' => 'floppy-disk',
    'sticky-note' => 'note-sticky',
    'window-close' => 'rectangle-xmark',
    'hand-holding-usd' => 'hand-holding-dollar',
    'user-friends' => 'user-group',
    'ellipsis-h' => 'ellipsis',

    // Additional FA5 names still used in UI
    'th-large' => 'table-cells-large',
    'tasks' => 'list-check',
    'cog' => 'gear',
    'cogs' => 'gears',
    'stream' => 'bars-staggered',
    'file-upload' => 'file-arrow-up',
    'file-download' => 'file-arrow-down',
    'university' => 'building-columns',
    'home' => 'house',
    'rupee-sign' => 'indian-rupee-sign',
    'file-archive' => 'file-zipper',
];

uksort($map, fn ($a, $b) => strlen($b) <=> strlen($a));

$roots = [
    __DIR__.'/../../resources/views',
    __DIR__.'/../../app',
    __DIR__.'/../../public/js',
];

$extraFiles = [
    __DIR__.'/../../public/colour5.html',
];

$changedFiles = 0;
$totalReplacements = 0;

$process = function (string $path) use ($map, &$changedFiles, &$totalReplacements): void {
    if (! is_file($path)) {
        return;
    }
    if (str_contains($path, 'tinymce') || str_contains($path, 'node_modules')) {
        return;
    }
    // Never rewrite the rename map / config keys
    if (str_ends_with(str_replace('\\', '/', $path), 'config/font_awesome.php')) {
        return;
    }

    $content = file_get_contents($path);
    $original = $content;
    $fileCount = 0;

    foreach ($map as $from => $to) {
        if ($from === $to) {
            continue;
        }
        $n = 0;
        $content = preg_replace(
            '/\bfa-'.preg_quote($from, '/').'\b/',
            'fa-'.$to,
            $content,
            -1,
            $n
        );
        $fileCount += $n;
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        $changedFiles++;
        $totalReplacements += $fileCount;
        echo $path.' ('.$fileCount.')'.PHP_EOL;
    }
};

foreach ($roots as $root) {
    if (! is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! preg_match('/\.(php|js)$/', $file->getFilename())) {
            continue;
        }
        $process($file->getPathname());
    }
}

foreach ($extraFiles as $path) {
    $process($path);
}

echo "DONE files={$changedFiles} replacements={$totalReplacements}".PHP_EOL;
