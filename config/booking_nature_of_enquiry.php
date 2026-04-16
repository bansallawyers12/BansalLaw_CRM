<?php

/**
 * CRM Nature of Enquiry (noe_id) for client appointment booking.
 * IDs are stored on booking_appointments.noe_id (tinyInteger).
 * enquiry_type is sent to external booking APIs where applicable.
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
        ['id' => 9, 'label' => 'Migration Advice', 'service_type' => 'Migration Advice', 'enquiry_type' => 'migration_advice'],
        ['id' => 10, 'label' => 'Migration Consultation', 'service_type' => 'Migration Consultation', 'enquiry_type' => 'migration_consultation'],
        ['id' => 11, 'label' => 'Student visa/ Admission', 'service_type' => 'Student visa/ Admission', 'enquiry_type' => 'education'],
        ['id' => 12, 'label' => 'Tourist visa', 'service_type' => 'Tourist visa', 'enquiry_type' => 'tourist'],
    ],
];
