<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = __DIR__.'/../public/colour5.html';
$content = file_get_contents($path);

$content = preg_replace('/\bfas fa-/', 'fa-solid fa-', $content);
$content = preg_replace('/\bfar fa-/', 'fa-regular fa-', $content);
$content = preg_replace('/\bfab fa-/', 'fa-brands fa-', $content);
$content = preg_replace('/\bfa fa-/', 'fa-solid fa-', $content);

foreach (config('font_awesome.icon_renames') as $old => $new) {
    $content = preg_replace('/\bfa-'.preg_quote($old, '/').'\b/', 'fa-'.$new, $content);
}

file_put_contents($path, $content);
echo "colour5.html migrated\n";
