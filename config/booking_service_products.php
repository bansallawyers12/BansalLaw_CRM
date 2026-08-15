<?php

/**
 * Public / CRM consultation products aligned with bansallawyers.com.au/book-an-appointment.
 *
 * Form / API `service_id` (1–3) maps to DB `booking_appointments.service_id`:
 *   form 1 → DB 2 (free), form 2 → DB 1 (paid 30), form 3 → DB 3 (extended 60).
 * CRM modal uses slugs: promo_free | paid | paid_extended.
 */
return [
    'by_form_id' => [
        1 => [
            'db_service_id' => 2,
            'slug' => 'promo_free',
            'name' => 'Free Consultation',
            'price' => 0,
            'price_display' => 'FREE',
            'duration_minutes' => 10,
            'specific_service' => 'consultation',
            'includes_video_call' => false,
            'description' => 'Short first consultation for new enquiries. Quick assessment of your matter and recommended next steps.',
        ],
        2 => [
            'db_service_id' => 1,
            'slug' => 'paid',
            'name' => 'Standard Consultation',
            'price' => 150,
            'price_display' => '$150',
            'duration_minutes' => 30,
            'specific_service' => 'paid-consultation',
            'includes_video_call' => true,
            'description' => 'In-depth legal consultation covering your matter, options, and a clear action plan. Available by phone, video, or in person in Melbourne.',
        ],
        3 => [
            'db_service_id' => 3,
            'slug' => 'paid_extended',
            'name' => 'Extended Consultation',
            'price' => 220,
            'price_display' => '$220',
            'duration_minutes' => 60,
            'specific_service' => 'extended-consultation',
            'includes_video_call' => true,
            'description' => 'One-hour consultation for complex or multi-issue matters. Available by phone, video, or in person in Melbourne.',
        ],
    ],
];
