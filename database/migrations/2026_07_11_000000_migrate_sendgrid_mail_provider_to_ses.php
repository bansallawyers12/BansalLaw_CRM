<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migrate legacy SendGrid mail_provider values to AWS SES.
     */
    public function up(): void
    {
        DB::table('emails')
            ->where('mail_provider', 'sendgrid')
            ->update(['mail_provider' => 'ses']);
    }

    /**
     * Reverse the migrations.
     *
     * Note: cannot distinguish rows originally created as SES vs migrated from SendGrid.
     */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
