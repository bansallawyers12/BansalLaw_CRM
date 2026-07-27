<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        '\App\Console\Commands\CronJob',
        //'\App\Console\Commands\CompleteTaskRemoval',

        '\App\Console\Commands\InPersonCompleteTaskRemoval',
        '\App\Console\Commands\ProcessServiceAccountTokens',
        '\App\Console\Commands\MigrateSecondDatabase',
        '\App\Console\Commands\CleanUtf8Data',
        
        // Appointment Sync System Commands
        '\App\Console\Commands\SyncBansalAppointments',
        '\App\Console\Commands\SendAppointmentReminders',
        '\App\Console\Commands\SendCourtHearingReminders',
        '\App\Console\Commands\TestBansalApiConnection',
        '\App\Console\Commands\BackfillBansalAppointments',
        
        // Signature Management System Commands
        '\App\Console\Commands\ArchiveOldDrafts',
        '\App\Console\Commands\SendSignatureReminders',
        
        // SQL Migration Tools
        //'\App\Console\Commands\FixMySqlDumpForPostgres', // Command file does not exist
        //'\App\Console\Commands\FixRemainingSqlIssues', // Command file does not exist
        
        // Login Data Import
        '\App\Console\Commands\ImportLoginDataFromMySQL',
        '\App\Console\Commands\ImportReferenceMasterData',
        
        // Client Reference Management Commands
        '\App\Console\Commands\FixDuplicateClientReferences',
        
        // Client Age Management Commands
        '\App\Console\Commands\UpdateClientAges',
        
        // Activity Cleanup Commands
        '\App\Console\Commands\CleanupActivityDescriptions',
        
        // Database Comparison
        '\App\Console\Commands\CompareDatabaseTables',
        '\App\Console\Commands\CheckMigrationTablesExist',
        '\App\Console\Commands\MarkMigrationsAsRunCommand',

        '\App\Console\Commands\ExpireCrmAccessGrants',
        '\App\Console\Commands\CacheAccessGrantGlobalCounts',

        '\App\Console\Commands\BackfillEmailPdfPreviews',

        '\App\Console\Commands\SyncInboxEmails',

        // Font Awesome FA6 migration (one-time DB icon class updates)
        '\App\Console\Commands\MigrateFontAwesomeDbIcons',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
	$schedule->command('CronJob:cronjob')->daily();
        

        //InPerson Complete Task Removal daily 1 time
        /*$schedule->command('InPersonCompleteTaskRemoval:daily')->daily();
        // Appointment Sync System - Sync from public booking website every 5 minutes (look back 24 hours)
        $schedule->command('booking:sync-appointments --minutes=1440')
            ->everyFiveMinutes()
            ->withoutOverlapping(5) // Max 5 minutes lock time
            ->appendOutputTo(storage_path('logs/appointment-sync.log'));
        
        
        // Appointment Sync System - Send reminders daily at 9 AM
        $schedule->command('booking:send-reminders')
            ->dailyAt('09:00')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/appointment-reminders.log'));
        */

        // Court hearing SMS reminders to clients (1 hr / 1 day / 1 week before)
        $schedule->command('court-hearings:send-reminders')
            ->everyFifteenMinutes()
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/court-hearing-reminders.log'));
        
        // Signature Management - Archive old drafts daily at 2 AM
        /*$schedule->command('signatures:archive-drafts --days=30')
            ->daily()
            ->at('02:00')
            ->timezone('Australia/Melbourne')
            ->appendOutputTo(storage_path('logs/signature-archive.log'));
        
        // Signature Management - Send auto-reminders daily at 10 AM
        $schedule->command('signatures:send-auto-reminders --days=7')
            ->daily()
            ->at('10:00')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/signature-auto-reminders.log'));*/
        
        // Client Age Management - Update ages bi-weekly (1st and 15th of each month) at 2 AM
       /* $schedule->command('clients:update-ages --smart')
            ->twiceMonthly(1, 15)
            ->at('02:00')
            ->timezone('Australia/Melbourne')
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/age-updates.log'));*/

        $schedule->command('access:expire-grants')->hourly();
        $schedule->command('access:cache-grant-stats')->hourly();

        if (config('imap_sync.enabled', true)) {
            $minutes = max(1, (int) config('imap_sync.schedule_minutes', 5));

            // One scheduled task per active mailbox so each account syncs
            // independently (own overlap lock) and logs to its own file.
            $mailboxes = $this->syncEnabledMailboxes();

            if ($mailboxes === []) {
                // Fallback: DB unavailable or no mailboxes flagged — sync everything in one run.
                $schedule->command('emails:sync-inbox')
                    ->cron('*/' . $minutes . ' * * * *')
                    ->withoutOverlapping(10)
                    ->appendOutputTo(storage_path('logs/inbox-sync/email-inbox-sync.log'));

                $schedule->command('emails:sync-inbox --full')
                    ->dailyAt('02:00')
                    ->timezone('Australia/Melbourne')
                    ->withoutOverlapping(60)
                    ->appendOutputTo(storage_path('logs/inbox-sync/email-inbox-sync.log'));
            } else {
                foreach ($mailboxes as $address) {
                    $logFile = storage_path('logs/inbox-sync/email-inbox-sync-' . $this->mailboxLogSlug($address) . '.log');

                    $schedule->command('emails:sync-inbox', [$address])
                        ->cron('*/' . $minutes . ' * * * *')
                        ->withoutOverlapping(10)
                        ->appendOutputTo($logFile);

                    // Nightly full sync: resets UID tracking and backfills recent mail so
                    // anything missed during the day (parser downtime etc.) is recovered.
                    $schedule->command('emails:sync-inbox', [$address, '--full'])
                        ->dailyAt('02:00')
                        ->timezone('Australia/Melbourne')
                        ->withoutOverlapping(60)
                        ->appendOutputTo($logFile);
                }
            }
        }
    }

    /**
     * Active mailbox addresses that have IMAP sync enabled.
     *
     * @return list<string>
     */
    protected function syncEnabledMailboxes(): array
    {
        try {
            return \App\Models\Email::query()
                ->where('status', true)
                ->where('sync_enabled', true)
                ->orderBy('email')
                ->pluck('email')
                ->filter()
                ->map(fn ($email) => strtolower(trim((string) $email)))
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function mailboxLogSlug(string $address): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($address)), '-');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
       // $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
