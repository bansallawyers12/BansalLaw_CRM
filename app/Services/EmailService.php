<?php

namespace App\Services;

use App\Models\Email;
use Illuminate\Mail\Message;

class EmailService
{
    public function __construct(
        protected MailRoutingService $mailRouting
    ) {}

    /**
     * Get all active email configurations.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllActiveEmails()
    {
        return Email::where('status', true)
            ->select('id', 'email', 'display_name', 'mail_provider')
            ->get();
    }

    /**
     * Send an email using the specified email configuration.
     *
     * @param string $view
     * @param array $data
     * @param string $to
     * @param string $subject
     * @param string $fromEmailId
     * @return bool
     * @throws \Exception
     */
    public function sendEmail($view, $data, $to, $subject, $fromEmailId, $attachments = [], $cc = [], $bcc = [])
    {
        try {
            $emailConfig = Email::where('email', $fromEmailId)->first();
            $fromAddress = $emailConfig?->email ?? $fromEmailId;
            $fromName = $emailConfig?->display_name ?? config('mail.from.name');

            $this->mailRouting->sendClosure($view, $data, function (Message $message) use ($to, $subject, $fromAddress, $fromName, $attachments, $cc, $bcc) {
                $message->to($to)
                    ->subject($subject)
                    ->from($fromAddress, $fromName);

                if (!empty($cc)) {
                    $message->cc($cc);
                }

                if (!empty($bcc)) {
                    $message->bcc($bcc);
                }

                if (!empty($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (file_exists($attachment)) {
                            $message->attach($attachment);
                        }
                    }
                }
            }, $fromAddress);

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Email could not be sent: ' . $e->getMessage());
        }
    }
}
