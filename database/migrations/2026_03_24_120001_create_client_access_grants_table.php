<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_access_grants')) {
            return;
        }

        Schema::create('client_access_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('admin_id');
            $table->string('record_type', 10);
            $table->string('grant_type', 20);
            $table->string('access_type', 20);
            $table->string('status', 20)->default('pending');
            $table->string('quick_reason_code', 50)->nullable();
            $table->text('requester_note')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->string('office_label_snapshot', 255)->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('team_label_snapshot', 255)->nullable();
            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_staff_id')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
            $table->foreign('office_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('approved_by_staff_id')->references('id')->on('staff')->nullOnDelete();
        });

        Schema::table('client_access_grants', function (Blueprint $table) {
            $table->index(['staff_id', 'admin_id', 'status'], 'idx_cag_staff_admin_status');
            $table->index(['status', 'requested_at'], 'idx_cag_status_requested');
            $table->index(['status', 'approved_by_staff_id'], 'idx_cag_approver_queue');
            $table->index('ends_at', 'idx_cag_ends_at');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_cag_ends_at');
            DB::statement('CREATE INDEX idx_cag_ends_at ON client_access_grants (ends_at) WHERE status = \'active\'');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_access_grants');
    }
};
