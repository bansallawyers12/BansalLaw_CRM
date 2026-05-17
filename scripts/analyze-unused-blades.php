#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Heuristic-only: Blade files whose dot/slash names never appear quoted in scanned PHP/Blade.
 * Ignores dynamic view(...) names, Lang:: markdown paths, Volt, some Livewire conventions, etc.
 * Safe to delete only after human review.
 */
$base = dirname(__DIR__);
$views = $base . '/resources/views';

$blades = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($views, RecursiveDirectoryIterator::SKIP_DOTS)
);
$list = [];

foreach ($blades as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }
    if (!str_ends_with((string) $file->getFilename(), '.blade.php')) {
        continue;
    }
    $realPath = $file->getRealPath();
    if ($realPath === false) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($realPath, strlen($views) + 1));
    $noExt = preg_replace('#\.blade\.php$#', '', $rel);
    if (!is_string($noExt)) {
        continue;
    }
    $list[] = [
        'path' => 'resources/views/' . $rel,
        'dot' => str_replace('/', '.', $noExt),
        'slash' => $noExt,
    ];
}

$dirs = [
    $base . '/app',
    $base . '/routes',
    $base . '/config',
    $base . '/resources/views',
    $base . '/database',
    $base . '/bootstrap',
    $base . '/tests',
];
$exts = ['php', 'blade.php'];
$corp = '';

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $fn = $file->getFilename();
        if (str_starts_with((string) $fn, '.')) {
            continue;
        }
        $rp = $file->getRealPath();
        if ($rp === false) {
            continue;
        }
        $needle = DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
        if (str_contains($rp, $needle)
            || str_contains($rp, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $ok = false;
        foreach ($exts as $ext) {
            if (str_ends_with((string) $fn, $ext)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            continue;
        }
        $text = file_get_contents($rp);
        if ($text !== false) {
            $corp .= $text;
        }
    }
}

$publicPhp = $base . '/public';
if (is_dir($publicPhp)) {
    foreach ((array) glob($publicPhp . '/*.php') as $path) {
        $t = @file_get_contents($path);
        if ($t !== false) {
            $corp .= $t;
        }
    }
}

/**
 * Laravel view alias without path context (relative strings return null).
 */
function normalize_flat_view_reference(string $ref): ?string
{
    $ref = trim($ref);
    if ($ref === '') {
        return null;
    }
    if (preg_match('#^[./\\\\]#', $ref)) {
        return null;
    }

    // pagination::bootstrap-4 -> vendor/pagination/bootstrap-4
    if (str_contains($ref, '::')) {
        [$ns, $path] = explode('::', $ref, 2);
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        return 'vendor/' . strtolower(trim($ns)) . '/' . str_replace('.', '/', $path);
    }

    return str_replace('.', '/', $ref);
}

/**
 * Resolve @include('../../foo.bar') paths relative to the including template directory.
 *
 * @param string $bladeNoSlashPath path under views without .blade.php, slashes forward
 */
function resolve_relative_blade_include(string $bladeNoSlashPath, string $ref): string
{
    $ref = str_replace('\\', '/', $ref);
    $baseDir = dirname($bladeNoSlashPath);
    $combined = ($baseDir === '.' ? '' : $baseDir . '/') . $ref;
    $parts = [];
    foreach (explode('/', $combined) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $segment;
    }

    return implode('/', $parts);
}

// --- Collect referenced slash-paths under resources/views/*.blade.php (no extension) ---
$referenced = [];

$addReferenced = static function (?string $key) use (&$referenced): void {
    if ($key === null || $key === '') {
        return;
    }
    $key = strtolower(str_replace('\\', '/', $key));
    $referenced[$key] = true;
};

preg_match_all('/\bview\s*\(\s*[\'"]([^\'"\\\\]*)[\'"]/', $corp, $mView);
preg_match_all('/\b(?:View::(?:make|first|exists))\s*\(\s*[\'"]([^\'"\\\\]*)[\'"]/', $corp, $mViewFacade);
preg_match_all('/\bloadView\s*\(\s*[\'"]([^\'"\\\\]*)[\'"]/', $corp, $mLoadView);
preg_match_all('/Route::view\s*\(\s*[^,]+,\s*[\'"]([^\'"\\\\]*)[\'"]/', $corp, $mRouteView);
preg_match_all('/@(?:extends|include(?:Once)?)\(\s*[\'"]([^\'"\\\\]*)[\'"]/', $corp, $mBladeDirect);
preg_match_all('/@component\s*\(\s*[\'"]([^\'"\\\\]*)[\'"]/', $corp, $mComponent);

foreach ($mView[1] as $tok) {
    $n = normalize_flat_view_reference($tok);
    if ($n !== null) {
        $addReferenced($n);
    }
}

foreach ($mViewFacade[1] as $tok) {
    $n = normalize_flat_view_reference($tok);
    if ($n !== null) {
        $addReferenced($n);
    }
}

foreach ($mLoadView[1] as $tok) {
    $n = normalize_flat_view_reference($tok);
    if ($n !== null) {
        $addReferenced($n);
    }
}

foreach ($mRouteView[1] as $tok) {
    $n = normalize_flat_view_reference($tok);
    if ($n !== null) {
        $addReferenced($n);
    }
}

foreach ($mBladeDirect[1] as $tok) {
    $n = normalize_flat_view_reference($tok);
    if ($n !== null) {
        $addReferenced($n);
    }
}

preg_match_all('/->view\s*\(\s*[\'"]([^\'"\\\\]*)[\'"]/', $corp, $mRespView);
foreach ($mRespView[1] as $tok) {
    $n = normalize_flat_view_reference($tok);
    if ($n !== null) {
        $addReferenced($n);
    }
}

foreach ($mComponent[1] as $tok) {
    $n = normalize_flat_view_reference($tok);
    if ($n !== null) {
        $addReferenced($n);
    }
}

// Anonymous / class Blade components <x-ns.part />
preg_match_all('/<x-([a-z][a-z0-9_.:-]*)(\s|\\/|>)/', $corp, $mXC);
foreach ($mXC[1] as $tag) {
    if (str_contains($tag, '::')) {
        continue;
    }
    // Anonymous components: tag "dashboard.kpi-card" → views/components/dashboard/kpi-card.blade.php
    $addReferenced(strtolower('components/' . str_replace('.', '/', $tag)));
}

// Second pass: relative @include /@extends resolved per template file.
foreach ($list as $entry) {
    $full = $views . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['slash']) . '.blade.php';
    if (!is_readable($full)) {
        continue;
    }
    $inner = file_get_contents($full);
    if ($inner === false) {
        continue;
    }
    preg_match_all('/@(?:extends|include(?:Once)?)\(\s*[\'"]([^\'"\\\\]*)[\'"]/', $inner, $local);
    foreach ($local[1] as $tok) {
        if (preg_match('#^[./\\\\]#', trim($tok))) {
            $res = strtolower(resolve_relative_blade_include($entry['slash'], $tok));
            $referenced[$res] = true;
        }
    }
}

// --- Unused: no keyed reference ---
$unused = [];
foreach ($list as $entry) {
    $key = strtolower($entry['slash']);
    if (!isset($referenced[$key])) {
        $unused[] = $entry['path'];
    }
}

sort($unused);

fwrite(STDERR, sprintf("%d blade files scanned\n", count($list)));
fwrite(STDERR, sprintf("%d likely-unused after view/include/extends/route::view scraping (still heuristic)\n\n", count($unused)));

foreach ($unused as $u) {
    echo $u . PHP_EOL;
}
