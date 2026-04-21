<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub / partial `email_logs` (renamed from mail_reports) may lack legacy CRM columns.
 * {@see \App\Models\EmailLog} expects these for compose, inbox, and history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        if (! Schema::hasColumn('email_logs', 'user_id')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('email_logs', 'from_mail')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->string('from_mail', 512)->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'to_mail')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->text('to_mail')->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'cc')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->text('cc')->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'template_id')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('template_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('email_logs', 'reciept_id')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('reciept_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('email_logs', 'subject')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->string('subject', 512)->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'type')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->string('type', 64)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('email_logs', 'message')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->longText('message')->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'attachments')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->longText('attachments')->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'mail_type')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->string('mail_type', 64)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('email_logs', 'client_id')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('email_logs', 'client_matter_id')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('client_matter_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('email_logs', 'conversion_type')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->string('conversion_type', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'mail_body_type')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->string('mail_body_type', 32)->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'fetch_mail_sent_time')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->timestamp('fetch_mail_sent_time')->nullable();
            });
        }

        if (! Schema::hasColumn('email_logs', 'uploaded_doc_id')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('uploaded_doc_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('email_logs', 'mail_is_read')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->boolean('mail_is_read')->default(false);
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: these columns may pre-exist on some DBs; do not drop data.
    }
};
