<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EmailSync\IncomingEmailSyncService;
use Illuminate\Http\Request;

$staffEmail = $argv[1] ?? 'admin@bansallawyers.com.au';
$staff = \App\Models\Staff::whereRaw('LOWER(email) = ?', [strtolower($staffEmail)])->first();

if (! $staff) {
    echo "Staff not found for {$staffEmail}\n";
    exit(1);
}

$addresses = IncomingEmailSyncService::mailboxAddressesForStaff((int) $staff->id, $staff->email);
if ($addresses === [] && trim((string) $staff->email) !== '') {
    $addresses = [strtolower(trim((string) $staff->email))];
}
echo "Staff #{$staff->id} ({$staff->email}) mailboxes:\n";
echo json_encode($addresses, JSON_PRETTY_PRINT) . "\n\n";

foreach (\App\Models\Email::where('status', true)->orderBy('email')->get() as $row) {
    echo "  mailbox {$row->email} sync_enabled=" . (int) $row->sync_enabled
        . ' owner_staff=' . ($row->resolveOwnerStaffId() ?? 'none') . "\n";
}
echo "\n";

$request = Request::create('/clients/synced-emails/sync-now', 'POST', [
    'today' => '1',
    'email' => $addresses[0] ?? '',
]);
echo 'Request today flag: ' . ($request->boolean('today', true) ? 'yes' : 'no') . "\n";
echo 'Request email: ' . $request->input('email', '') . "\n\n";

$since = $request->boolean('today', true)
    ? now((string) config('app.timezone', 'UTC'))->startOfDay()
    : null;

$email = trim((string) $request->input('email', ''));
$summary = app(IncomingEmailSyncService::class)->syncAll($email !== '' ? $email : null, $since);
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
