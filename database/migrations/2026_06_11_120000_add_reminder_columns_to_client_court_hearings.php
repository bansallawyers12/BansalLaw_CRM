<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_court_hearings')) {
            return;
        }

        Schema::table('client_court_hearings', function (Blueprint $table) {
            if (! Schema::hasColumn('client_court_hearings', 'reminder_minutes')) {
                $table->unsignedSmallInteger('reminder_minutes')->nullable()->after('status');
            }
            if (! Schema::hasColumn('client_court_hearings', 'reminder_sms_sent_at')) {
                $table->dateTime('reminder_sms_sent_at')->nullable()->after('reminder_minutes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_court_hearings')) {
            return;
        }

        Schema::table('client_court_hearings', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('client_court_hearings', 'reminder_sms_sent_at')) {
                $columns[] = 'reminder_sms_sent_at';
            }
            if (Schema::hasColumn('client_court_hearings', 'reminder_minutes')) {
                $columns[] = 'reminder_minutes';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
