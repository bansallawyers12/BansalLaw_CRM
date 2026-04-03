<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\ClientEmail;
use Carbon\Carbon;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $clientEmail;
    public $verificationUrl;
    public $expiresAt;

    /**
     * Create a new message instance.
     */
    public function __construct(ClientEmail $clientEmail, $verificationUrl, Carbon $expiresAt)
    {
        $this->clientEmail = $clientEmail;
        $this->verificationUrl = $verificationUrl;
        $this->expiresAt = $expiresAt;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject('Verify Your Email Address - BANSAL Immigration')
                    ->view('emails.email_verification');
    }
}
