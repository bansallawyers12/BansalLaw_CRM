<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'google_review_reminder_status')) {
                $table->string('google_review_reminder_status', 32)->nullable();
            }
            if (! Schema::hasColumn('admins', 'google_review_reminder_snooze_until')) {
                $table->timestamp('google_review_reminder_snooze_until')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'google_review_reminder_snooze_until')) {
                $table->dropColumn('google_review_reminder_snooze_until');
            }
            if (Schema::hasColumn('admins', 'google_review_reminder_status')) {
                $table->dropColumn('google_review_reminder_status');
            }
        });
    }
};
