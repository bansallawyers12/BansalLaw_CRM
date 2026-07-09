<?php

$renames = [
    'fa-clock-o' => 'fa-clock',
    'fa-pencil-alt' => 'fa-pen',
    'fa-trash-alt' => 'fa-trash-can',
    'fa-file-text' => 'fa-file-lines',
    'fa-arrows-alt' => 'fa-up-down-left-right',
    'fa-ellipsis-v' => 'fa-ellipsis-vertical',
    'fa-thumb-tack' => 'fa-thumbtack',
    'fa-plus-circle' => 'fa-circle-plus',
];

$prefixPatterns = [
    '/(?<![\w-])fas fa-/' => 'fa-solid fa-',
    '/(?<![\w-])far fa-/' => 'fa-regular fa-',
    '/(?<![\w-])fab fa-/' => 'fa-brands fa-',
    '/(?<![\w-])fa fa-/' => 'fa-solid fa-',
];

$roots = [
    __DIR__.'/../app',
    __DIR__.'/../public/js',
    __DIR__.'/../config',
    __DIR__.'/../database/migrations',
];

$extensions = ['php', 'js'];
$changed = 0;
$files = [];

foreach ($roots as $root) {
    if (! is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! in_array($file->getExtension(), $extensions, true)) {
            continue;
        }

        $files[] = $file->getPathname();
    }
}

foreach ($files as $path) {
    $content = file_get_contents($path);
    $original = $content;

    foreach ($prefixPatterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    foreach ($renames as $from => $to) {
        $content = str_replace($from, $to, $content);
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        $changed++;
    }
}

echo "Updated {$changed} PHP/JS files\n";
