<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy stub migrations created `emails` with only id/timestamps.
     * Outbound SendGrid / CRM code expects these columns.
     */
    public function up(): void
    {
        if (! Schema::hasTable('emails')) {
            return;
        }

        Schema::table('emails', function (Blueprint $table) {
            if (! Schema::hasColumn('emails', 'email')) {
                $table->string('email', 255)->nullable()->index();
            }
            if (! Schema::hasColumn('emails', 'display_name')) {
                $table->string('display_name', 255)->nullable();
            }
            if (! Schema::hasColumn('emails', 'status')) {
                $table->boolean('status')->default(true)->index();
            }
            if (! Schema::hasColumn('emails', 'email_signature')) {
                $table->text('email_signature')->nullable();
            }
            if (! Schema::hasColumn('emails', 'user_id')) {
                // Admin UI stores JSON array of staff ids (see EmailController store/update).
                $table->text('user_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('emails')) {
            return;
        }

        Schema::table('emails', function (Blueprint $table) {
            foreach (['email_signature', 'user_id', 'status', 'display_name', 'email'] as $col) {
                if (Schema::hasColumn('emails', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
