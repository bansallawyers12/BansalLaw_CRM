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
            if (! Schema::hasColumn('staff', 'can_pause_mailbox_inbox_sync')) {
                $table->boolean('can_pause_mailbox_inbox_sync')
                    ->default(false)
                    ->after('can_view_all_synced_inbox_mail');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'can_pause_mailbox_inbox_sync')) {
                $table->dropColumn('can_pause_mailbox_inbox_sync');
            }
        });
    }
};
