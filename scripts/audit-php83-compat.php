<?php

$lock = json_decode(file_get_contents(__DIR__ . '/../composer.lock'), true);
$packages = array_merge($lock['packages'], $lock['packages-dev'] ?? []);

$incompatible = [];
$allows84Only = [];

foreach ($packages as $package) {
    $req = $package['require']['php'] ?? null;
    if ($req === null) {
        continue;
    }

    // Packages that explicitly require PHP 8.4+ and exclude 8.3.
    if (preg_match('/(?:>=|~|\^)8\.4|>=8\.5|>=9\.|\^9\.|~9\.0/', $req)
        && ! preg_match('/8\.3|\^8\.2|\^8\.1|\^8\.0|>=8\.[0123]|8\.2\s*-/', $req)) {
        $incompatible[] = sprintf('%s@%s requires %s', $package['name'], $package['version'], $req);
    }

    if (preg_match('/8\.4|8\.5/', $req) && preg_match('/8\.3/', $req)) {
        $allows84Only[] = sprintf('%s@%s allows 8.3+ (%s)', $package['name'], $package['version'], $req);
    }
}

$composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
$platformPhp = $composer['config']['platform']['php'] ?? ($lock['platform']['php'] ?? 'not pinned');

echo 'Installed packages: ' . count($packages) . PHP_EOL;
echo 'Project PHP constraint: ' . ($composer['require']['php'] ?? 'n/a') . PHP_EOL;
echo 'Composer platform pin: ' . $platformPhp . PHP_EOL . PHP_EOL;

if ($incompatible === []) {
    echo "PASS: No installed package requires PHP 8.4+ exclusively.\n";
} else {
    echo "FAIL: Packages incompatible with PHP 8.3:\n";
    echo implode(PHP_EOL, $incompatible) . PHP_EOL;
    exit(1);
}

echo 'INFO: ' . count($allows84Only) . " packages also allow PHP 8.4/8.5 (still OK on 8.3).\n";
