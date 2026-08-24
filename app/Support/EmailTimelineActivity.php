<?php

namespace App\Support;

final class EmailTimelineActivity
{
    public static function subject(string $verb, string $emailSubject, ?string $matterReference = null): string
    {
        $emailSubject = trim($emailSubject) !== '' ? trim($emailSubject) : 'Email';
        $matterReference = trim((string) $matterReference);

        $subject = $matterReference !== ''
            ? "{$verb} Email: {$emailSubject} - {$matterReference}"
            : "{$verb} Email: {$emailSubject}";

        if (strlen($subject) > 100) {
            return substr($subject, 0, 97) . '...';
        }

        return $subject;
    }

    public static function subjectReceived(string $emailSubject, ?string $matterReference = null): string
    {
        return self::subject('received', $emailSubject, $matterReference);
    }

    public static function subjectUploaded(string $emailSubject, ?string $matterReference = null): string
    {
        return self::subject('uploaded', $emailSubject, $matterReference);
    }

    public static function subjectAssigned(string $emailSubject, ?string $matterReference = null): string
    {
        return self::subject('assigned', $emailSubject, $matterReference);
    }

    public static function subjectSent(string $emailSubject, ?string $matterReference = null): string
    {
        return self::subject('sent', $emailSubject, $matterReference);
    }

    public static function descriptionFrom(string $from): string
    {
        $from = trim($from) !== '' ? trim($from) : 'Unknown';

        return '<p>From: ' . htmlspecialchars($from, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    public static function descriptionTo(string $to): string
    {
        $to = trim($to) !== '' ? trim($to) : 'Unknown';

        return '<p>To: ' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '</p>';
    }
}
