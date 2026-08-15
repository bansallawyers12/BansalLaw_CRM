<?php

namespace App\Support;

/**
 * Shared NOE / service product helpers for Lawyers booking (CRM + public API).
 */
final class BookingCatalogue
{
    /**
     * @return list<array{id:int,label:string,service_type:string,enquiry_type:string}>
     */
    public static function crmNatureOfEnquiry(): array
    {
        return array_values(config('booking_nature_of_enquiry.crm', []));
    }

    /**
     * @return list<int>
     */
    public static function crmNoeIds(): array
    {
        return array_map('intval', array_column(self::crmNatureOfEnquiry(), 'id'));
    }

    public static function noeRow(int $noeId): ?array
    {
        foreach (self::crmNatureOfEnquiry() as $row) {
            if ((int) $row['id'] === $noeId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Practice-area labels for public select_your_service (same ids as CRM).
     *
     * @return list<array{id:int,name:string}>
     */
    public static function publicSelectYourService(): array
    {
        return array_map(static function (array $row) {
            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['label'],
            ];
        }, self::crmNatureOfEnquiry());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function publicServiceTypeList(): array
    {
        $out = [];
        foreach (config('booking_service_products.by_form_id', []) as $formId => $product) {
            $duration = (int) $product['duration_minutes'];
            $out[] = [
                'id' => (int) $formId,
                'name' => $product['name'],
                'price' => (float) $product['price'],
                'price_display' => $product['price_display'],
                'duration' => $duration,
                'duration_unit' => 'minutes',
                'time_slots' => [
                    'start_time' => $formId === 1 ? '10:45' : '09:00',
                    'end_time' => $formId === 1 ? '16:00' : '17:00',
                    'time_format' => 'AM/PM',
                ],
                'availability' => [
                    'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    'time_slots' => $duration . '-minute time slots',
                ],
                'description' => $product['description'],
                'includes_video_call' => (bool) $product['includes_video_call'],
                'available_for_overseas' => false,
            ];
        }

        return $out;
    }

    /**
     * Melbourne-only location list (Lawyers booking). Id 2 kept for CRM/slot compatibility.
     *
     * @return list<array<string, mixed>>
     */
    public static function publicLocations(): array
    {
        return [
            [
                'id' => 2,
                'name' => 'Melbourne Office',
                'address' => 'Level 8/278 Collins St',
                'city' => 'Melbourne',
                'state' => 'VIC',
                'postcode' => '3000',
                'full_address' => 'Level 8/278 Collins St Melbourne VIC 3000',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function productByFormId(int $formId): ?array
    {
        $row = config('booking_service_products.by_form_id.' . $formId);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function productBySlug(string $slug): ?array
    {
        foreach (config('booking_service_products.by_form_id', []) as $product) {
            if (($product['slug'] ?? null) === $slug) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function productByDbServiceId(int $dbServiceId): ?array
    {
        foreach (config('booking_service_products.by_form_id', []) as $product) {
            if ((int) ($product['db_service_id'] ?? 0) === $dbServiceId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Resolve CRM modal / HomeController service key (slug or numeric form id) to product.
     *
     * @return array<string, mixed>|null
     */
    public static function productFromRequestServiceKey(mixed $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }
        if (is_numeric($key)) {
            return self::productByFormId((int) $key);
        }

        return self::productBySlug((string) $key);
    }

    /**
     * Slot / Bansal specific_service for a form id or CRM slug.
     */
    public static function specificServiceForRequestKey(mixed $key): string
    {
        $product = self::productFromRequestServiceKey($key);

        return $product['specific_service'] ?? 'consultation';
    }

    /**
     * enquiry_item (NOE id) → enquiry_type / service_type string for slot APIs.
     */
    public static function slotServiceTypeForNoeId(mixed $enquiryItem): string
    {
        $noeId = (int) $enquiryItem;
        $row = self::noeRow($noeId);
        if ($row) {
            return (string) $row['enquiry_type'];
        }

        return 'criminal_law';
    }

    /**
     * Always Melbourne for Lawyers booking (Adelaide retired from this flow).
     */
    public static function locationFromInpersonAddress(mixed $inpersonAddress): string
    {
        return 'melbourne';
    }

    public static function inpersonAddressMelbourne(): int
    {
        return 2;
    }

    /**
     * Historical immigration NOE labels (noe_scheme = immigration only).
     *
     * @return array<int, array{service_type: string, enquiry_type: string}>
     */
    public static function immigrationNoeToServiceType(): array
    {
        return [
            1 => ['service_type' => 'Permanent Residency', 'enquiry_type' => 'pr_complex'],
            2 => ['service_type' => 'Temporary Residency', 'enquiry_type' => 'tr'],
            3 => ['service_type' => 'JRP/Skill Assessment', 'enquiry_type' => 'jrp'],
            4 => ['service_type' => 'Tourist Visa', 'enquiry_type' => 'tourist'],
            5 => ['service_type' => 'Education/Student Visa', 'enquiry_type' => 'education'],
            6 => ['service_type' => 'Complex Matters (AAT, Protection visa, Federal Case)', 'enquiry_type' => 'complex'],
            7 => ['service_type' => 'Visa Cancellation/NOICC/Refusals', 'enquiry_type' => 'cancellation'],
            8 => ['service_type' => 'INDIA/UK/CANADA/EUROPE TO AUSTRALIA', 'enquiry_type' => 'international'],
        ];
    }

    /**
     * @return array{service_type: string, enquiry_type: string}
     */
    public static function serviceTypeMappingForNoe(int $noeId, string $scheme = 'crm'): array
    {
        if ($scheme === 'immigration') {
            return self::immigrationNoeToServiceType()[$noeId]
                ?? ['service_type' => 'Other', 'enquiry_type' => 'pr_complex'];
        }

        $row = self::noeRow($noeId);
        if ($row) {
            return [
                'service_type' => (string) $row['service_type'],
                'enquiry_type' => (string) $row['enquiry_type'],
            ];
        }

        return ['service_type' => 'Other', 'enquiry_type' => 'general'];
    }

    /**
     * Display label for enquiry_type / noe on API responses.
     */
    public static function enquiryTypeDisplay(?string $enquiryType, ?int $noeId, ?string $scheme): string
    {
        if ($scheme === 'crm' || ($scheme === null && $noeId !== null && self::noeRow($noeId))) {
            if ($noeId !== null) {
                $row = self::noeRow($noeId);
                if ($row) {
                    return (string) $row['label'];
                }
            }
            $crm = collect(self::crmNatureOfEnquiry())->firstWhere('enquiry_type', $enquiryType);
            if ($crm) {
                return (string) $crm['label'];
            }
        }

        return match ($enquiryType) {
            'pr', 'pr_complex' => 'Permanent Residency',
            'tr' => 'Temporary Residency',
            'tourist' => 'Tourist Visa',
            'education' => 'Education/Student Visa',
            'jrp' => 'JRP/Skill Assessment',
            'complex' => 'Complex Matters (AAT, Protection visa, Federal Case)',
            'cancellation', 'visa_cancellation' => 'Visa Cancellation/NOICC/Refusals',
            'international', 'india_uk_canada_europe' => 'INDIA/UK/CANADA/EUROPE TO AUSTRALIA',
            default => $enquiryType ? ucfirst(str_replace('_', ' ', $enquiryType)) : 'General',
        };
    }

    public static function isPaidDbServiceId(?int $dbServiceId): bool
    {
        return in_array((int) $dbServiceId, [1, 3], true);
    }

    /**
     * Allowed paid amounts for Lawyers products ($150 or $220).
     *
     * @return list<float>
     */
    public static function allowedPaidAmounts(): array
    {
        return [150.0, 220.0];
    }
}
