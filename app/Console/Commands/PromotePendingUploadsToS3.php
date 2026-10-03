<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Staff;
use App\Services\CrmDurableStorage;
use App\Services\MailRoutingService;
use App\Services\SystemBreakdownService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PromotePendingUploadsToS3 extends Command
{
    protected $signature = 'storage:promote-pending-to-s3 
                            {--force-alert : Force sending failure alert regardless of cooldown}';

    protected $description = 'Upload local-only CRM files to S3 when the bucket is available with automated administrator failure alerts';

    public function handle(CrmDurableStorage $storage): int
    {
        if (! $storage->usesCloud()) {
            $this->warn('S3 is not configured; nothing to promote.');

            return self::SUCCESS;
        }

        $stats = $storage->promoteAllPendingToCloud();

        $summary = sprintf(
            'Promote complete: scanned=%d promoted=%d skipped=%d failed=%d',
            $stats['scanned'],
            $stats['promoted'],
            $stats['skipped'],
            $stats['failed']
        );

        if ($stats['failed'] > 0) {
            $this->error($summary);

            $this->line('');
            $this->error('The following file(s) failed cloud promotion:');
            foreach (array_slice($stats['errors'] ?? [], 0, 15) as $err) {
                $this->line(sprintf('  - %s: %s', $err['path'], $err['error']));
            }
            if (count($stats['errors'] ?? []) > 15) {
                $this->line(sprintf('  ... and %d more files.', count($stats['errors']) - 15));
            }

            // 1. Log failure to Laravel log
            Log::error('S3 durable upload promotion encountered failures', [
                'stats' => $stats,
                'sample_errors' => array_slice($stats['errors'] ?? [], 0, 10),
            ]);

            // 2. Record in System Breakdown Monitor (/system-errors)
            $this->recordSystemBreakdown($stats);

            // 3. Dispatch automated alerts (Slack/Webhook, Email, In-App Notification)
            $this->dispatchFailureAlerts($stats);

            return self::FAILURE;
        }

        // On complete success, clear alert cooldown if one was previously recorded
        Cache::forget('s3_promote_failure_alert_last_sent');

        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * Record failure in the System Breakdown Monitor (/system-errors).
     *
     * @param  array{promoted: int, skipped: int, failed: int, scanned: int, errors: list<array{path: string, error: string}>}  $stats
     */
    protected function recordSystemBreakdown(array $stats): void
    {
        try {
            SystemBreakdownService::ensureTableExists();

            $firstError = $stats['errors'][0] ?? ['path' => 'unknown', 'error' => 'S3 upload failed'];
            $exception = new \RuntimeException(sprintf(
                'S3 Background Promotion Failed: %d of %d scanned files failed cloud upload. Sample failure on [%s]: %s',
                $stats['failed'],
                $stats['scanned'],
                $firstError['path'],
                $firstError['error']
            ));

            SystemBreakdownService::recordException($exception);
        } catch (\Throwable $e) {
            Log::warning('Failed recording S3 promotion error in SystemBreakdownService: ' . $e->getMessage());
        }
    }

    /**
     * Dispatch multi-channel administrator failure alerts.
     *
     * @param  array{promoted: int, skipped: int, failed: int, scanned: int, errors: list<array{path: string, error: string}>}  $stats
     */
    protected function dispatchFailureAlerts(array $stats): void
    {
        $cooldownMinutes = (int) config('crm.durable_storage.alert_cooldown_minutes', 60);
        $cacheKey = 's3_promote_failure_alert_last_sent';
        $force = (bool) $this->option('force-alert');

        if (! $force && Cache::has($cacheKey)) {
            $this->warn(sprintf('Automated failure alert throttled (cooldown active: %d minutes).', $cooldownMinutes));

            return;
        }

        // 1. In-App Notifications for Super Admins and Admins
        if ((bool) config('crm.durable_storage.notify_in_app', true)) {
            $this->sendInAppNotifications($stats);
        }

        // 2. Webhook / Slack notification
        $webhookUrl = (string) config('crm.durable_storage.alert_webhook_url');
        if (! empty($webhookUrl)) {
            $this->sendWebhookAlert($webhookUrl, $stats);
        }

        // 3. Email notification to administrators
        $alertEmail = (string) config('crm.durable_storage.alert_email');
        if (! empty($alertEmail)) {
            $this->sendEmailAlert($alertEmail, $stats);
        }

        Cache::put($cacheKey, now()->toIso8601String(), now()->addMinutes($cooldownMinutes));
    }

    /**
     * Send in-app notification to all active Super Admins and Admins.
     *
     * @param  array{promoted: int, skipped: int, failed: int, scanned: int, errors: list<array{path: string, error: string}>}  $stats
     */
    protected function sendInAppNotifications(array $stats): void
    {
        try {
            $admins = Staff::query()
                ->whereIn('role', [1, 17])
                ->where('status', 1)
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            $senderId = $admins->firstWhere('role', 1)?->id ?? $admins->first()->id;
            $message = sprintf(
                '🚨 S3 Cloud Sync Alert: %d file(s) failed promotion to S3 bucket. Click to inspect system errors.',
                $stats['failed']
            );

            foreach ($admins as $admin) {
                // Avoid spamming identical unread notifications
                $alreadyPending = Notification::query()
                    ->where('receiver_id', $admin->id)
                    ->where('notification_type', 's3_promote_failure')
                    ->where('seen', 0)
                    ->exists();

                if ($alreadyPending) {
                    continue;
                }

                Notification::create([
                    'sender_id' => $senderId,
                    'receiver_id' => $admin->id,
                    'module_id' => null,
                    'url' => url('/system-errors'),
                    'notification_type' => 's3_promote_failure',
                    'message' => $message,
                    'seen' => 0,
                    'receiver_status' => 0,
                    'sender_status' => 1,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed creating in-app notification for S3 promote failure: ' . $e->getMessage());
        }
    }

    /**
     * Send Slack or generic incoming webhook alert.
     *
     * @param  array{promoted: int, skipped: int, failed: int, scanned: int, errors: list<array{path: string, error: string}>}  $stats
     */
    protected function sendWebhookAlert(string $webhookUrl, array $stats): void
    {
        try {
            $errorSnippets = [];
            foreach (array_slice($stats['errors'] ?? [], 0, 5) as $err) {
                $errorSnippets[] = '• `' . $err['path'] . '`: ' . $err['error'];
            }
            $errorText = implode("\n", $errorSnippets);
            if (count($stats['errors'] ?? []) > 5) {
                $errorText .= "\n• ... and " . (count($stats['errors']) - 5) . " more file(s).";
            }

            $payload = [
                'text' => sprintf('🚨 *Bansal Law CRM*: S3 Promotion Alert - %d file(s) failed cloud upload.', $stats['failed']),
                'attachments' => [
                    [
                        'color' => '#dc3545',
                        'title' => 'S3 Cloud Storage Promotion Glitch Detected',
                        'title_link' => url('/system-errors'),
                        'fields' => [
                            [
                                'title' => 'Environment',
                                'value' => (string) config('app.env', 'production'),
                                'short' => true,
                            ],
                            [
                                'title' => 'Bucket',
                                'value' => (string) (config('filesystems.disks.s3.bucket') ?: 'Not configured'),
                                'short' => true,
                            ],
                            [
                                'title' => 'Summary',
                                'value' => sprintf('Failed: *%d* | Promoted: *%d* | Scanned: *%d*', $stats['failed'], $stats['promoted'], $stats['scanned']),
                                'short' => false,
                            ],
                            [
                                'title' => 'Sample Errors',
                                'value' => $errorText ?: 'No specific error message returned.',
                                'short' => false,
                            ],
                        ],
                        'footer' => 'Bansal Law CRM Durable Storage Sync',
                        'ts' => time(),
                    ],
                ],
            ];

            Http::timeout(10)->post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning('Failed sending S3 promote webhook alert: ' . $e->getMessage());
        }
    }

    /**
     * Send email alert to system administrators.
     *
     * @param  array{promoted: int, skipped: int, failed: int, scanned: int, errors: list<array{path: string, error: string}>}  $stats
     */
    protected function sendEmailAlert(string $alertEmail, array $stats): void
    {
        try {
            $recipients = array_values(array_filter(array_map('trim', explode(',', $alertEmail))));
            if (empty($recipients)) {
                return;
            }

            $errorSnippets = [];
            foreach (array_slice($stats['errors'] ?? [], 0, 10) as $err) {
                $errorSnippets[] = "- " . $err['path'] . ": " . $err['error'];
            }
            $errorBody = implode("\n", $errorSnippets);

            $subject = sprintf('[%s] ALERT: %d File(s) Failed S3 Cloud Promotion', strtoupper((string) config('app.env')), $stats['failed']);
            $body = "Dear Administrator,\n\n"
                . "The background S3 cloud promotion job encountered errors while syncing durable local files to Amazon S3.\n\n"
                . "Promotion Statistics:\n"
                . "- Scanned: " . $stats['scanned'] . "\n"
                . "- Promoted: " . $stats['promoted'] . "\n"
                . "- Skipped: " . $stats['skipped'] . "\n"
                . "- Failed: " . $stats['failed'] . "\n\n"
                . "Sample Errors:\n" . $errorBody . "\n\n"
                . "Please inspect the server logs at storage/logs/durable-storage-promote.log or view the System Error Monitor:\n"
                . url('/system-errors') . "\n\n"
                . "--\nBansal Lawyers CRM Automated Storage Monitor";

            $mailer = app(MailRoutingService::class);
            $mailer->sendTo($recipients, function ($message) use ($recipients, $subject, $body) {
                $message->to($recipients)
                    ->subject($subject)
                    ->text($body);
            }, forceSystem: true);
        } catch (\Throwable $e) {
            Log::warning('Failed sending S3 promote email alert: ' . $e->getMessage());
        }
    }
}
