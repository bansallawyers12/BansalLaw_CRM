<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientCourtHearing;
use App\Services\Sms\UnifiedSmsManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CourtHearingReminderService
{
    public const ALLOWED_REMINDER_MINUTES = [60, 1440, 10080];

    public function __construct(protected UnifiedSmsManager $smsManager)
    {
    }

    /**
     * @return array{total: int, sent: int, failed: int, skipped: int}
     */
    public function sendDueReminders(): array
    {
        $now = Carbon::now(config('app.timezone'));
        $stats = ['total' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        $hearings = ClientCourtHearing::query()
            ->with('client')
            ->where('status', 'Scheduled')
            ->whereNotNull('reminder_minutes')
            ->whereIn('reminder_minutes', self::ALLOWED_REMINDER_MINUTES)
            ->whereNull('reminder_sms_sent_at')
            ->get();

        foreach ($hearings as $hearing) {
            $startsAt = $hearing->hearingStartsAt();

            if ($startsAt->lte($now)) {
                $stats['skipped']++;

                continue;
            }

            $reminderDueAt = $startsAt->copy()->subMinutes((int) $hearing->reminder_minutes);

            if ($now->lt($reminderDueAt)) {
                continue;
            }

            $stats['total']++;

            if ($this->sendReminderSms($hearing, $startsAt)) {
                $stats['sent']++;
            } else {
                $stats['failed']++;
            }
        }

        Log::info('Court hearing reminder run completed', $stats);

        return $stats;
    }

    public function sendReminderSms(ClientCourtHearing $hearing, ?Carbon $startsAt = null): bool
    {
        if ($hearing->reminder_sms_sent_at !== null) {
            return true;
        }

        $client = $hearing->client;
        if (! $client instanceof Admin) {
            Log::warning('Court hearing reminder skipped — client not found', [
                'court_hearing_id' => $hearing->id,
                'client_id' => $hearing->client_id,
            ]);

            return false;
        }

        $phone = trim((string) ($client->country_code ?? '') . (string) ($client->phone ?? ''));
        if ($phone === '') {
            Log::warning('Court hearing reminder skipped — no client phone', [
                'court_hearing_id' => $hearing->id,
                'client_id' => $hearing->client_id,
            ]);

            return false;
        }

        $startsAt ??= $hearing->hearingStartsAt();
        $variables = $this->templateVariables($hearing, $client, $startsAt);
        $context = [
            'client_id' => $hearing->client_id,
        ];

        $result = $this->smsManager->sendFromTemplateByAlias(
            $phone,
            'court_hearing_reminder',
            $variables,
            $context
        );

        if (! $result['success'] && $this->isTemplateMissingError($result)) {
            $message = sprintf(
                'BANSAL LAWYERS: Reminder — your court hearing is on %s at %s at %s. Call %s if you have questions.',
                $variables['hearing_date'],
                $variables['hearing_time'],
                $variables['court_name'],
                $variables['office_phone']
            );
            $result = $this->smsManager->sendSms($phone, $message, 'reminder', $context);
        }

        if ($result['success']) {
            $hearing->update(['reminder_sms_sent_at' => now()]);

            Log::info('Court hearing reminder SMS sent', [
                'court_hearing_id' => $hearing->id,
                'client_id' => $hearing->client_id,
            ]);

            return true;
        }

        Log::error('Court hearing reminder SMS failed', [
            'court_hearing_id' => $hearing->id,
            'client_id' => $hearing->client_id,
            'message' => $result['message'] ?? 'Unknown error',
        ]);

        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function templateVariables(ClientCourtHearing $hearing, Admin $client, Carbon $startsAt): array
    {
        $tz = config('app.timezone');
        $local = $startsAt->copy()->timezone($tz);

        return [
            'first_name' => trim((string) ($client->first_name ?? '')) ?: 'there',
            'hearing_date' => $local->format('l, d M Y'),
            'hearing_time' => $hearing->hearing_time
                ? $local->format('g:i A')
                : '9:00 AM',
            'court_name' => trim((string) ($hearing->court_name ?? '')) ?: 'court',
            'office_phone' => '1300 859 368',
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function isTemplateMissingError(array $result): bool
    {
        $message = strtolower((string) ($result['message'] ?? ''));

        return str_contains($message, 'template not found') || str_contains($message, 'inactive');
    }
}
