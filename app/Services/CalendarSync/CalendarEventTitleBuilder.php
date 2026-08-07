<?php

namespace App\Services\CalendarSync;

use App\Models\BookingAppointment;
use App\Models\ClientCourtHearing;
use App\Models\ClientMatter;
use App\Models\StaffCalendarEvent;

/**
 * Build Outlook/Zoho titles that include CRM file number + matter so staff can match events.
 * Format: [FileNo / MatterRef] Title
 */
class CalendarEventTitleBuilder
{
    public static function forStaffEvent(StaffCalendarEvent $event): string
    {
        $fileRef = self::fileRefFromClientId($event->client_id);
        $matterRef = self::matterRef($event->client_matter_id, $event->relationLoaded('matter') ? $event->matter : null);

        return self::compose($event->title ?? 'Event', $fileRef, $matterRef);
    }

    public static function forHearing(ClientCourtHearing $hearing): string
    {
        $type = trim((string) ($hearing->hearing_type ?: 'Court hearing'));
        $court = trim((string) ($hearing->court_name ?: ''));
        $title = $court !== '' ? ($type . ' @ ' . $court) : $type;

        $refs = self::refsForHearing($hearing);

        return self::compose($title, $refs['file_ref'], $refs['matter_ref']);
    }

    public static function forBooking(BookingAppointment $appointment): string
    {
        $name = trim((string) ($appointment->client_name ?: 'Appointment'));
        $service = trim((string) ($appointment->service_type ?: $appointment->enquiry_type ?: ''));
        $title = $service !== '' ? ($name . ' — ' . $service) : $name;

        $refs = self::refsForBooking($appointment);

        return self::compose($title, $refs['file_ref'], $refs['matter_ref']);
    }

    public static function compose(string $title, ?string $fileRef, ?string $matterRef): string
    {
        $title = trim($title);
        if ($title === '') {
            $title = 'Event';
        }

        $fileRef = $fileRef !== null ? trim($fileRef) : '';
        $matterRef = $matterRef !== null ? trim($matterRef) : '';

        if ($fileRef === '' && $matterRef === '') {
            return $title;
        }

        if ($fileRef !== '' && $matterRef !== '') {
            return sprintf('[%s / %s] %s', $fileRef, $matterRef, $title);
        }

        return sprintf('[%s] %s', $fileRef !== '' ? $fileRef : $matterRef, $title);
    }

    public static function fileRefFromClientId(null|int|string $clientId): ?string
    {
        if ($clientId === null || $clientId === '') {
            return null;
        }

        return (string) $clientId;
    }

    public static function matterRef(?int $clientMatterId, ?ClientMatter $matter = null): ?string
    {
        if ($matter instanceof ClientMatter) {
            foreach (['client_unique_matter_no', 'case_detail'] as $field) {
                $value = $matter->{$field} ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $trimmed = trim($value);
                    if ($field === 'case_detail' && strlen($trimmed) > 40) {
                        $trimmed = substr($trimmed, 0, 37) . '...';
                    }

                    return $trimmed;
                }
            }
        }

        if ($clientMatterId) {
            return 'M' . $clientMatterId;
        }

        return null;
    }

    /**
     * @return array{file_ref: string|null, matter_ref: string|null}
     */
    public static function refsForStaffEvent(StaffCalendarEvent $event): array
    {
        return [
            'file_ref' => self::fileRefFromClientId($event->client_id),
            'matter_ref' => self::matterRef(
                $event->client_matter_id,
                $event->relationLoaded('matter') ? $event->matter : null
            ),
        ];
    }

    /**
     * @return array{file_ref: string|null, matter_ref: string|null}
     */
    public static function refsForHearing(ClientCourtHearing $hearing): array
    {
        return [
            'file_ref' => self::fileRefFromClientId($hearing->client_id),
            'matter_ref' => self::matterRef(
                $hearing->client_matter_id,
                $hearing->relationLoaded('matter') ? $hearing->matter : null
            ),
        ];
    }

    /**
     * @return array{file_ref: string|null, matter_ref: string|null}
     */
    public static function refsForBooking(BookingAppointment $appointment): array
    {
        return [
            'file_ref' => self::fileRefFromClientId($appointment->client_id),
            'matter_ref' => null,
        ];
    }
}
