<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        Schema::table('email_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('email_logs', 'send_status')) {
                $table->string('send_status', 20)->default('sent')->index();
            }
            if (! Schema::hasColumn('email_logs', 'send_error')) {
                $table->text('send_error')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'bcc')) {
                $table->text('bcc')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'failed_at')) {
                $table->timestamp('failed_at')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'retry_count')) {
                $table->unsignedTinyInteger('retry_count')->default(0);
            }
            if (! Schema::hasColumn('email_logs', 'resend_of_id')) {
                $table->unsignedBigInteger('resend_of_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        Schema::table('email_logs', function (Blueprint $table) {
            foreach (['send_status', 'send_error', 'bcc', 'sent_at', 'failed_at', 'retry_count', 'resend_of_id'] as $column) {
                if (Schema::hasColumn('email_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
