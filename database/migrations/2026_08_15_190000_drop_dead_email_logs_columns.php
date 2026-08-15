<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop unused email_logs leftover columns:
 * python_rendering, enhanced_html, rendered_html, last_accessed_at,
 * resend_of_id, reciept_id, attachments (text; files live in email_log_attachments),
 * thread_id.
 *
 * Leaves compose send_status / send_error / sent_at / failed_at / retry_count
 * and Python analysis columns that still have write paths.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'python_rendering',
        'enhanced_html',
        'rendered_html',
        'last_accessed_at',
        'resend_of_id',
        'reciept_id',
        'attachments',
        'thread_id',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        foreach (['email_logs_resend_of_id_index', 'email_logs_reciept_id_index', 'email_logs_thread_id_index'] as $index) {
            if (Schema::hasIndex('email_logs', $index)) {
                Schema::table('email_logs', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        $drop = array_values(array_filter(
            self::COLUMNS,
            fn (string $c) => Schema::hasColumn('email_logs', $c)
        ));
        if ($drop === []) {
            return;
        }

        Schema::table('email_logs', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        Schema::table('email_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('email_logs', 'python_rendering')) {
                $table->json('python_rendering')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'enhanced_html')) {
                $table->longText('enhanced_html')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'rendered_html')) {
                $table->longText('rendered_html')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'last_accessed_at')) {
                $table->timestamp('last_accessed_at')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'resend_of_id')) {
                $table->unsignedBigInteger('resend_of_id')->nullable()->index();
            }
            if (! Schema::hasColumn('email_logs', 'reciept_id')) {
                $table->unsignedBigInteger('reciept_id')->nullable()->index();
            }
            if (! Schema::hasColumn('email_logs', 'attachments')) {
                $table->longText('attachments')->nullable();
            }
            if (! Schema::hasColumn('email_logs', 'thread_id')) {
                $table->string('thread_id')->nullable()->index();
            }
        });
    }
};
