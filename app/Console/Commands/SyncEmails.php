<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncEmails extends Command
{
    protected $signature = 'emails:sync {id? : Deprecated — use emails:sync-inbox instead}';

    protected $description = 'Deprecated. Use emails:sync-inbox to fetch mail from Zoho IMAP.';

    public function handle(): int
    {
        $this->warn('The emails:sync command is deprecated.');
        $this->line('Use: php artisan emails:sync-inbox');
        $this->line('Optional: php artisan emails:sync-inbox user@bansallawyers.com.au --full');

        return self::FAILURE;
    }
}
