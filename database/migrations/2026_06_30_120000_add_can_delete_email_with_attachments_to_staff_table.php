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
            if (! Schema::hasColumn('staff', 'can_delete_email_with_attachments')) {
                $table->boolean('can_delete_email_with_attachments')
                    ->default(false)
                    ->after('grant_super_admin_access');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'can_delete_email_with_attachments')) {
                $table->dropColumn('can_delete_email_with_attachments');
            }
        });
    }
};
