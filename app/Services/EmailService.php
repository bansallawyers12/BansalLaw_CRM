<?php

namespace App\Services;

use App\Models\Email;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class EmailService
{
    /**
     * Get all active email configurations.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllActiveEmails()
    {
        return Email::where('status', true)
            ->select('id', 'email', 'display_name')
            ->get();
    }

    /**
     * Send an email using the specified email configuration.
     *
     * @param string $view
     * @param array $data
     * @param string $to
     * @param string $subject
     * @param int $fromEmailId
     * @return bool
     * @throws \Exception
     */
    public function sendEmail($view, $data, $to, $subject, $fromEmailId, $attachments = [], $cc = [])
    {
        try {
            $emailConfig = Email::where('email', $fromEmailId)->first();
            $fromAddress = $emailConfig?->email ?? config('mail.from.address');
            $fromName = $emailConfig?->display_name ?? config('mail.from.name');

            // Send the email
            Mail::mailer('sendgrid')->send($view, $data, function (Message $message) use ($to, $subject, $fromAddress, $fromName, $attachments, $cc) {
                $message->to($to)
                    ->subject($subject)
                    ->from($fromAddress, $fromName);

                if (!empty($cc)) {
                    $message->cc($cc);
                }

                if (!empty($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (file_exists($attachment)) {
                            $message->attach($attachment);
                        }
                    }
                }
            });

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Email could not be sent: ' . $e->getMessage());
        }
    }
}
