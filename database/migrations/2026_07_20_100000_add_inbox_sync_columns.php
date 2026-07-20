<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('emails')) {
            Schema::table('emails', function (Blueprint $table) {
                if (! Schema::hasColumn('emails', 'sync_enabled')) {
                    $table->boolean('sync_enabled')->default(true)->after('status');
                }
                if (! Schema::hasColumn('emails', 'last_synced_at')) {
                    $table->timestamp('last_synced_at')->nullable()->after('sync_enabled');
                }
                if (! Schema::hasColumn('emails', 'last_imap_uid')) {
                    $table->unsignedBigInteger('last_imap_uid')->nullable()->after('last_synced_at');
                }
                if (! Schema::hasColumn('emails', 'last_sync_error')) {
                    $table->text('last_sync_error')->nullable()->after('last_imap_uid');
                }
                if (! Schema::hasColumn('emails', 'imap_host')) {
                    $table->string('imap_host')->nullable()->after('last_sync_error');
                }
                if (! Schema::hasColumn('emails', 'imap_port')) {
                    $table->unsignedSmallInteger('imap_port')->nullable()->default(993)->after('imap_host');
                }
                if (! Schema::hasColumn('emails', 'imap_encryption')) {
                    $table->string('imap_encryption', 10)->nullable()->default('ssl')->after('imap_port');
                }
            });
        }

        if (Schema::hasTable('email_logs')) {
            Schema::table('email_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('email_logs', 'mailbox_email')) {
                    $table->string('mailbox_email')->nullable()->index()->after('from_mail');
                }
                if (! Schema::hasColumn('email_logs', 'synced_email_id')) {
                    $table->unsignedBigInteger('synced_email_id')->nullable()->index()->after('mailbox_email');
                }
                if (! Schema::hasColumn('email_logs', 'sync_assignment_status')) {
                    $table->string('sync_assignment_status', 30)->nullable()->index()->after('synced_email_id');
                }
                if (! Schema::hasColumn('email_logs', 'imap_uid')) {
                    $table->unsignedBigInteger('imap_uid')->nullable()->after('sync_assignment_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('emails')) {
            Schema::table('emails', function (Blueprint $table) {
                foreach ([
                    'sync_enabled',
                    'last_synced_at',
                    'last_imap_uid',
                    'last_sync_error',
                    'imap_host',
                    'imap_port',
                    'imap_encryption',
                ] as $col) {
                    if (Schema::hasColumn('emails', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('email_logs')) {
            Schema::table('email_logs', function (Blueprint $table) {
                foreach ([
                    'mailbox_email',
                    'synced_email_id',
                    'sync_assignment_status',
                    'imap_uid',
                ] as $col) {
                    if (Schema::hasColumn('email_logs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
