<?php

namespace App\Jobs;

use App\Services\MailRoutingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued CRM email with correct Zoho/SES mailer applied at execution time.
 */
class SendCrmEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string|array $to,
        public Mailable $mailable,
        public ?string $fromAddress = null,
        public bool $forceSystem = false,
    ) {}

    public function handle(MailRoutingService $routing): void
    {
        $routing->mailer($this->fromAddress, $this->forceSystem)
            ->to($this->to)
            ->sendNow($this->mailable);
    }
}
