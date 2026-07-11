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
     */
    public function down(): void
    {
        DB::table('emails')
            ->where('mail_provider', 'ses')
            ->update(['mail_provider' => 'sendgrid']);
    }
};
