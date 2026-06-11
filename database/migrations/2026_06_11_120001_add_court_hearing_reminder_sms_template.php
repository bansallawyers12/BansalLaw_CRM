<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('sms_templates')) {
            return;
        }

        if (DB::table('sms_templates')->where('alias', 'court_hearing_reminder')->exists()) {
            return;
        }

        $now = now();
        DB::table('sms_templates')->insert([
            'title' => 'Court Hearing Reminder',
            'message' => 'BANSAL LAWYERS: Reminder — your court hearing is on {hearing_date} at {hearing_time} at {court_name}. Call {office_phone} if you have questions.',
            'variables' => 'first_name,hearing_date,hearing_time,court_name,office_phone',
            'category' => 'reminder',
            'alias' => 'court_hearing_reminder',
            'is_active' => true,
            'usage_count' => 0,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('sms_templates')) {
            return;
        }

        DB::table('sms_templates')->where('alias', 'court_hearing_reminder')->delete();
    }
};
