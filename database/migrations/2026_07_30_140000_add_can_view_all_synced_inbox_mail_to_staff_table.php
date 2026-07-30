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
            if (! Schema::hasColumn('staff', 'can_view_all_synced_inbox_mail')) {
                $table->boolean('can_view_all_synced_inbox_mail')
                    ->default(false)
                    ->after('can_sync_inbox_emails');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'can_view_all_synced_inbox_mail')) {
                $table->dropColumn('can_view_all_synced_inbox_mail');
            }
        });
    }
};
