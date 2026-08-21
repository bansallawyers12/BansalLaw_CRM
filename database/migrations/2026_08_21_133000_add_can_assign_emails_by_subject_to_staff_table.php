<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'can_assign_emails_by_subject')) {
                $table->boolean('can_assign_emails_by_subject')
                    ->default(false)
                    ->after('can_pause_mailbox_inbox_sync');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'can_assign_emails_by_subject')) {
                $table->dropColumn('can_assign_emails_by_subject');
            }
        });
    }
};
