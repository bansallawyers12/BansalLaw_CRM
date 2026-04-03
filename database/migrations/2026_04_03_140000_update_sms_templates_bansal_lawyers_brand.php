<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align SMS template copy with APP_NAME branding (e.g. BANSAL Lawyers).
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('sms_templates')) {
            return;
        }

        $replacements = [
            'BANSAL IMMIGRATION:' => 'BANSAL LAWYERS:',
            'Bansal Immigration' => 'BANSAL Lawyers',
        ];

        $rows = DB::table('sms_templates')->get(['id', 'message']);
        foreach ($rows as $row) {
            $message = $row->message;
            foreach ($replacements as $from => $to) {
                $message = str_replace($from, $to, $message);
            }
            if ($message !== $row->message) {
                DB::table('sms_templates')->where('id', $row->id)->update([
                    'message' => $message,
                    'updated_at' => now(),
                ]);
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

        $replacements = [
            'BANSAL LAWYERS:' => 'BANSAL IMMIGRATION:',
            'BANSAL Lawyers' => 'Bansal Immigration',
        ];

        $rows = DB::table('sms_templates')->get(['id', 'message']);
        foreach ($rows as $row) {
            $message = $row->message;
            foreach ($replacements as $from => $to) {
                $message = str_replace($from, $to, $message);
            }
            if ($message !== $row->message) {
                DB::table('sms_templates')->where('id', $row->id)->update([
                    'message' => $message,
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
