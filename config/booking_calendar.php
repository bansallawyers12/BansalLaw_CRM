<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Calendar event source
    |--------------------------------------------------------------------------
    |
    | local  — booking_appointments only (default)
    | external — Bansal public appointment API (APPOINTMENT_API_* in services.php)
    | merge — local rows plus API rows not yet stored in CRM (by bansal_appointment_id)
    |
    */
    'data_source' => env('BOOKING_CALENDAR_DATA_SOURCE', 'local'),

    /**
     * When true, the calendar JSON feed includes appointments in FullCalendar’s visible [start, end) window
     * even if they are before “today” (e.g. earlier days in the current month). When false (default),
     * only today and future appointments are returned (legacy behaviour).
     */
    'include_past_in_visible_range' => filter_var(
        env('BOOKING_CALENDAR_INCLUDE_PAST_IN_RANGE', false),
        FILTER_VALIDATE_BOOL
    ),

    'external' => [
        'default_service_id' => (int) env('BOOKING_CALENDAR_SERVICE_ID', 1),
        'service_ids_by_type' => [
            'ajay' => env('BOOKING_CALENDAR_AJAY_SERVICE_ID'),
            'kunal' => env('BOOKING_CALENDAR_KUNAL_SERVICE_ID'),
        ],
        /** Query parameter sent to /appointments (e.g. active flag on the booking site API). */
        'api_status_filter' => env('BOOKING_CALENDAR_EXTERNAL_STATUS', 1),
        /** Send FullCalendar range as extra query params (names configurable). */
        'pass_calendar_range' => filter_var(env('BOOKING_CALENDAR_EXTERNAL_PASS_RANGE', true), FILTER_VALIDATE_BOOL),
        'range_param_start' => env('BOOKING_CALENDAR_EXTERNAL_RANGE_START', 'start_date'),
        'range_param_end' => env('BOOKING_CALENDAR_EXTERNAL_RANGE_END', 'end_date'),
        /**
         * If true, summary-card stats requests omit the API "status" query param (some sites return [] when it is set).
         * Calendar events still use BOOKING_CALENDAR_EXTERNAL_STATUS.
         */
        'stats_omit_status_param' => filter_var(env('BOOKING_CALENDAR_STATS_OMIT_STATUS', false), FILTER_VALIDATE_BOOL),
    ],

];
