<?php

/**
 * CRM Nature of Enquiry (noe_id) for client appointment booking.
 * IDs are stored on booking_appointments.noe_id (tinyInteger).
 * enquiry_type is sent to external booking APIs where applicable.
 *
 * Source of truth: bansallawyers.com.au/book-an-appointment (practice areas 1–7).
 * Historical immigration visa catalogue used noe_id 1–8 with noe_scheme=immigration.
 */
return [
    'crm' => [
        ['id' => 1, 'label' => 'Criminal Law', 'service_type' => 'Criminal Law', 'enquiry_type' => 'criminal_law'],
        ['id' => 2, 'label' => 'Family Law', 'service_type' => 'Family Law', 'enquiry_type' => 'family_law'],
        ['id' => 3, 'label' => 'Corporate Law', 'service_type' => 'Corporate Law', 'enquiry_type' => 'corporate_law'],
        ['id' => 4, 'label' => 'Personal Law', 'service_type' => 'Personal Law', 'enquiry_type' => 'personal_law'],
        ['id' => 5, 'label' => 'Immigration Law', 'service_type' => 'Immigration Law', 'enquiry_type' => 'immigration_law'],
        ['id' => 6, 'label' => 'Property Law', 'service_type' => 'Property Law', 'enquiry_type' => 'property_law'],
        ['id' => 7, 'label' => 'Commercial Law', 'service_type' => 'Commercial Law', 'enquiry_type' => 'commercial_law'],
    ],
];
