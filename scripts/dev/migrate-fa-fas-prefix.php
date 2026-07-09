<?php

$patterns = [
    'class="fas ' => 'class="fa-solid ',
    "class='fas " => "class='fa-solid ",
    '<i class="fas ' => '<i class="fa-solid ',
    "<i class='fas " => "<i class='fa-solid ",
    "'fas ' +" => "'fa-solid ' +",
    '"fas ' .'"' => '"fa-solid ' .'"', // unlikely
    '"fas " +' => '"fa-solid " +',
    "'<i class=\"fas '" => "'<i class=\"fa-solid '",
    '<i class="fas ${' => '<i class="fa-solid ${',
    '<i class="fas {{' => '<i class="fa-solid {{',
    '<span><i class="fas ${' => '<span><i class="fa-solid ${',
    '<div class="grid_icon"><i class="fas ' => '<div class="grid_icon"><i class="fa-solid ',
];

$roots = [
    __DIR__.'/../resources/views',
    __DIR__.'/../public/js',
    __DIR__.'/../app',
];

$changed = 0;

foreach ($roots as $root) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $ext = $file->getExtension();
        if (! in_array($ext, ['php', 'js'], true)) {
            continue;
        }

        $path = $file->getPathname();
        $content = file_get_contents($path);
        $original = $content;

        foreach ($patterns as $from => $to) {
            $content = str_replace($from, $to, $content);
        }

        if ($content !== $original) {
            file_put_contents($path, $content);
            $changed++;
        }
    }
}

echo "Fixed standalone fas prefix in {$changed} files\n";
