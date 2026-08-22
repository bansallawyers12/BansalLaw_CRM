<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CronJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CronJob:cronjob';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Legacy daily cron stub (invoice reminders removed)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Invoice reminder logic removed — invoice_payments, invoice_details, and invoices tables dropped.
        $this->info('CronJob:cronjob completed (no scheduled work).');
    }
}
