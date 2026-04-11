<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop all columns that existed solely to support the external client portal
 * mobile app — which has been fully removed from this codebase.
 *
 * Affected tables / columns:
 *   admins            — cp_status, cp_random_code, cp_code_verify, cp_token_generated_at
 *   documents         — is_client_portal_verify
 *   account_client_receipts — client_portal_sent, client_portal_sent_at,
 *                             client_portal_payment_token, client_portal_payment_type
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── admins ──────────────────────────────────────────────────────────
        Schema::table('admins', function (Blueprint $table) {
            $drop = [];
            foreach (['cp_status', 'cp_random_code', 'cp_code_verify', 'cp_token_generated_at'] as $col) {
                if (Schema::hasColumn('admins', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        // ── documents ───────────────────────────────────────────────────────
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'is_client_portal_verify')) {
                $table->dropColumn('is_client_portal_verify');
            }
        });

        // ── account_client_receipts ─────────────────────────────────────────
        Schema::table('account_client_receipts', function (Blueprint $table) {
            $drop = [];
            foreach ([
                'client_portal_sent',
                'client_portal_sent_at',
                'client_portal_payment_token',
                'client_portal_payment_type',
            ] as $col) {
                if (Schema::hasColumn('account_client_receipts', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        // ── admins ──────────────────────────────────────────────────────────
        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'cp_status')) {
                $table->tinyInteger('cp_status')->default(0)->after('status');
            }
            if (! Schema::hasColumn('admins', 'cp_random_code')) {
                $table->string('cp_random_code', 100)->nullable()->after('cp_status');
            }
            if (! Schema::hasColumn('admins', 'cp_code_verify')) {
                $table->tinyInteger('cp_code_verify')->default(0)->after('cp_random_code');
            }
            if (! Schema::hasColumn('admins', 'cp_token_generated_at')) {
                $table->timestamp('cp_token_generated_at')->nullable()->after('cp_code_verify');
            }
        });

        // ── documents ───────────────────────────────────────────────────────
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'is_client_portal_verify')) {
                $table->integer('is_client_portal_verify')->nullable();
            }
        });

        // ── account_client_receipts ─────────────────────────────────────────
        Schema::table('account_client_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('account_client_receipts', 'client_portal_sent')) {
                $table->tinyInteger('client_portal_sent')->default(0)->nullable();
            }
            if (! Schema::hasColumn('account_client_receipts', 'client_portal_sent_at')) {
                $table->timestamp('client_portal_sent_at')->nullable();
            }
            if (! Schema::hasColumn('account_client_receipts', 'client_portal_payment_token')) {
                $table->string('client_portal_payment_token', 500)->nullable();
            }
            if (! Schema::hasColumn('account_client_receipts', 'client_portal_payment_type')) {
                $table->string('client_portal_payment_type', 50)->nullable();
            }
        });
    }
};
