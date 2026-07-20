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
            if (! Schema::hasColumn('staff', 'can_sync_inbox_emails')) {
                $table->boolean('can_sync_inbox_emails')
                    ->default(false)
                    ->after('can_delete_email_with_attachments');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'can_sync_inbox_emails')) {
                $table->dropColumn('can_sync_inbox_emails');
            }
        });
    }
};
