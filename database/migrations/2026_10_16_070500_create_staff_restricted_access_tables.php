<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 — Restricted staff access (local-first).
 *
 * - staff_access_profiles: per-staff mode + dashboard widget allow-list
 * - staff_record_grants: explicit client / lead / matter allow-list + scopes
 * - staff_access_audit: who granted/revoked what
 *
 * Full CRM bypass is Super Admin only (enforced in later steps via config).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_access_profiles')) {
            Schema::create('staff_access_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                // full = existing CRM behaviour; restricted = profile + dashboard shell + grants only
                $table->string('access_mode', 20)->default('full');
                $table->json('dashboard_widgets')->nullable();
                $table->boolean('can_view_own_profile')->default(true);
                $table->boolean('can_view_dashboard')->default(true);
                $table->unsignedBigInteger('updated_by_staff_id')->nullable();
                $table->timestampsTz();

                $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
                $table->foreign('updated_by_staff_id')->references('id')->on('staff')->nullOnDelete();
                $table->index('access_mode', 'idx_sap_access_mode');
            });
        }

        if (! Schema::hasTable('staff_record_grants')) {
            Schema::create('staff_record_grants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('staff_id');
                // client | lead | matter
                $table->string('resource_type', 20);
                // admins.id for client/lead; client_matters.id for matter
                $table->unsignedBigInteger('resource_id');
                // e.g. ["view","emails","documents","notes"]
                $table->json('scopes')->nullable();
                $table->unsignedBigInteger('granted_by_staff_id')->nullable();
                $table->timestampTz('starts_at')->nullable();
                $table->timestampTz('ends_at')->nullable();
                $table->timestampTz('revoked_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestampsTz();

                $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
                $table->foreign('granted_by_staff_id')->references('id')->on('staff')->nullOnDelete();
                $table->unique(['staff_id', 'resource_type', 'resource_id'], 'uniq_srg_staff_resource');
                $table->index(['staff_id', 'resource_type'], 'idx_srg_staff_type');
                $table->index(['resource_type', 'resource_id'], 'idx_srg_resource');
                $table->index('ends_at', 'idx_srg_ends_at');
            });
        }

        if (! Schema::hasTable('staff_access_audit')) {
            Schema::create('staff_access_audit', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_staff_id')->nullable();
                $table->unsignedBigInteger('target_staff_id')->nullable();
                $table->string('action', 50);
                $table->string('resource_type', 20)->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('actor_staff_id')->references('id')->on('staff')->nullOnDelete();
                $table->foreign('target_staff_id')->references('id')->on('staff')->nullOnDelete();
                $table->index(['target_staff_id', 'created_at'], 'idx_saa_target_created');
                $table->index(['actor_staff_id', 'created_at'], 'idx_saa_actor_created');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_access_audit');
        Schema::dropIfExists('staff_record_grants');
        Schema::dropIfExists('staff_access_profiles');
    }
};
