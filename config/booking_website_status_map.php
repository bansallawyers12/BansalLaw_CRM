<?php

/**
 * Website / public booking API status codes (0–11) → CRM booking_appointments fields.
 *
 * @see \App\Services\Booking\BookingCalendarExternalFeed::websiteBookingsStatusLabels()
 */
return [
    'map' => [
        0 => ['status' => 'pending', 'payment_status' => 'pending', 'is_paid' => false],
        1 => ['status' => 'confirmed', 'is_paid' => false],
        2 => ['status' => 'completed', 'is_paid' => false],
        3 => ['status' => 'cancelled', 'is_paid' => false],
        4 => ['status' => 'pending', 'is_paid' => false],
        5 => ['status' => 'confirmed', 'is_paid' => false],
        6 => ['status' => 'no_show', 'is_paid' => false],
        7 => ['status' => 'cancelled', 'is_paid' => false],
        8 => ['status' => 'no_show', 'is_paid' => false],
        9 => ['status' => 'pending', 'payment_status' => 'pending', 'is_paid' => false],
        10 => ['status' => 'paid', 'payment_status' => 'completed', 'is_paid' => true],
        11 => ['status' => 'pending', 'payment_status' => 'failed', 'is_paid' => false],
    ],
];
