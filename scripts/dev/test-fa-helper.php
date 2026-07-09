<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Helpers\FontAwesomeHelper;

$cases = [
    'fas fa-inbox' => 'fa-solid fa-inbox',
    'fa-solid fa-file-alt' => 'fa-solid fa-file-lines',
    'fa-solid fa-external-link-alt' => 'fa-solid fa-up-right-from-square',
    'fa-solid fa-check-circle text-success' => 'fa-solid fa-circle-check text-success',
    'fa-file-alt' => 'fa-file-lines',
    'far fa-edit' => 'fa-regular fa-pen-to-square',
    'fa-solid fa-circle-check' => 'fa-solid fa-circle-check', // already FA6
];

$failed = 0;
foreach ($cases as $in => $expected) {
    $out = FontAwesomeHelper::migrateClasses($in);
    $ok = $out === $expected;
    echo ($ok ? 'OK  ' : 'FAIL')." {$in} => {$out}".($ok ? '' : " (expected {$expected})").PHP_EOL;
    if (! $ok) {
        $failed++;
    }
}

echo FontAwesomeHelper::iconClass('solid', 'clock-o').PHP_EOL;
echo FontAwesomeHelper::iconClass('solid', 'external-link-alt', true, 'fa-fw').PHP_EOL;

exit($failed > 0 ? 1 : 0);
