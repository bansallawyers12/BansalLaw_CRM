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

    /*
    |--------------------------------------------------------------------------
    | Website bookings admin list (GET /booking/appointments)
    |--------------------------------------------------------------------------
    |
    | Rows are loaded from the public booking API (APPOINTMENT_API_URL + /appointments).
    | When WEBSITE_BOOKINGS_API_SERVICE_ID is set, that service_id is sent on every request
    | (some APIs require it). Leave unset to omit service_id and request all services.
    |
    */
    'website_bookings_list' => [
        'api_service_id' => env('WEBSITE_BOOKINGS_API_SERVICE_ID'),
        /** Primary query names for the public /appointments API (mirrors are also sent). */
        'api_date_param_from' => env('WEBSITE_BOOKINGS_API_DATE_FROM_PARAM', 'date_from'),
        'api_date_param_to' => env('WEBSITE_BOOKINGS_API_DATE_TO_PARAM', 'date_to'),
        /**
         * When a from/to date is set, fetch multiple API pages (up to max), filter by date in PHP,
         * then paginate — fixes APIs that ignore date params or use different names.
         */
        'aggregate_when_date_filtered' => filter_var(
            env('WEBSITE_BOOKINGS_AGGREGATE_ON_DATE', true),
            FILTER_VALIDATE_BOOL
        ),
        'aggregated_fetch_max_api_pages' => (int) env('WEBSITE_BOOKINGS_AGGREGATE_MAX_API_PAGES', 40),
        'api_per_chunk' => (int) env('WEBSITE_BOOKINGS_API_PER_CHUNK', 100),
    ],

];
