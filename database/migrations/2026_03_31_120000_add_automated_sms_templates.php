<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Register automated SMS bodies in sms_templates so they can be edited in Admin Console.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('sms_templates')) {
            return;
        }

        DB::table('sms_templates')->where('alias', 'phone_verification')->update([
            'title' => 'Phone Verification Code',
            'message' => 'BANSAL IMMIGRATION: Your phone verification code is {verification_code}. Please provide this code to our staff to verify your phone number. This code expires in {expiry_minutes} minutes.',
            'variables' => 'verification_code,expiry_minutes',
            'category' => 'verification',
            'updated_at' => now(),
        ]);

        $now = now();
        $rows = [
            [
                'title' => 'Not Picked Call',
                'message' => "Hi {first_name},\n\nWe tried reaching you but couldn't connect. Please call us at {office_phone} or let us know a suitable time.\n\nPlease do not reply via SMS.\n\nBansal Immigration",
                'variables' => 'first_name,office_phone',
                'category' => 'notification',
                'alias' => 'not_picked_call',
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Booking Reminder — In Person',
                'message' => 'BANSAL IMMIGRATION: Reminder - You have a scheduled In-Person appointment tomorrow at {timeslot_full} at our {location} office. Please be on time. If you need to reschedule, call us at {office_phone}.',
                'variables' => 'timeslot_full,location,office_phone',
                'category' => 'reminder',
                'alias' => 'booking_reminder_in_person',
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Booking Reminder — Phone',
                'message' => 'BANSAL IMMIGRATION: Reminder - You have a scheduled Phone appointment tomorrow at {timeslot_full} . Please be on time. If you need to reschedule, call us at {office_phone}.',
                'variables' => 'timeslot_full,office_phone',
                'category' => 'reminder',
                'alias' => 'booking_reminder_phone',
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Booking Reminder — Video',
                'message' => 'BANSAL IMMIGRATION: Reminder - You have a scheduled Video Call appointment tomorrow at {timeslot_full} . Please be on time. If you need to reschedule, call us at {office_phone}.',
                'variables' => 'timeslot_full,office_phone',
                'category' => 'reminder',
                'alias' => 'booking_reminder_video',
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Booking Reminder — Default',
                'message' => 'BANSAL IMMIGRATION: Reminder - You have a scheduled appointment tomorrow at {timeslot_full} at our {location} office. Please be on time. If you need to reschedule, call us at {office_phone}.',
                'variables' => 'timeslot_full,location,office_phone',
                'category' => 'reminder',
                'alias' => 'booking_reminder_default',
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            if (! DB::table('sms_templates')->where('alias', $row['alias'])->exists()) {
                DB::table('sms_templates')->insert($row);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('sms_templates')) {
            return;
        }

        DB::table('sms_templates')->whereIn('alias', [
            'not_picked_call',
            'booking_reminder_in_person',
            'booking_reminder_phone',
            'booking_reminder_video',
            'booking_reminder_default',
        ])->delete();

        DB::table('sms_templates')->where('alias', 'phone_verification')->update([
            'message' => 'BANSAL IMMIGRATION: Your verification code is {verification_code}. This code expires in 5 minutes.',
            'variables' => 'verification_code',
            'updated_at' => now(),
        ]);
    }
};
