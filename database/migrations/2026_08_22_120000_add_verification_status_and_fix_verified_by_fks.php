<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add status lifecycle to verification audit tables and point verified_by at staff.
     */
    public function up(): void
    {
        if (! Schema::hasTable('phone_verifications') || ! Schema::hasTable('email_verifications')) {
            return;
        }

        Schema::table('phone_verifications', function (Blueprint $table) {
            if (! Schema::hasColumn('phone_verifications', 'status')) {
                $table->string('status', 20)->default('pending')->after('otp_code');
                $table->index('status');
            }
        });

        Schema::table('email_verifications', function (Blueprint $table) {
            if (! Schema::hasColumn('email_verifications', 'status')) {
                $table->string('status', 20)->default('pending')->after('verification_token');
                $table->index('status');
            }
        });

        DB::table('phone_verifications')
            ->where('is_verified', true)
            ->update(['status' => 'verified']);

        DB::table('phone_verifications')
            ->where('is_verified', false)
            ->where(function ($query) {
                $query->whereNull('otp_expires_at')
                    ->orWhere('otp_expires_at', '>', now());
            })
            ->update(['status' => 'pending']);

        DB::table('phone_verifications')
            ->where('is_verified', false)
            ->whereNotNull('otp_expires_at')
            ->where('otp_expires_at', '<=', now())
            ->update(['status' => 'expired']);

        DB::table('email_verifications')
            ->where('is_verified', true)
            ->update(['status' => 'verified']);

        DB::table('email_verifications')
            ->where('is_verified', false)
            ->where(function ($query) {
                $query->whereNull('token_expires_at')
                    ->orWhere('token_expires_at', '>', now());
            })
            ->update(['status' => 'pending']);

        DB::table('email_verifications')
            ->where('is_verified', false)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->retargetVerifiedByToStaff('client_contacts');
        $this->retargetVerifiedByToStaff('client_emails');
        $this->addStaffForeignKey('phone_verifications');
        $this->addStaffForeignKey('email_verifications');
    }

    public function down(): void
    {
        if (Schema::hasTable('phone_verifications') && Schema::hasColumn('phone_verifications', 'status')) {
            Schema::table('phone_verifications', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }

        if (Schema::hasTable('email_verifications') && Schema::hasColumn('email_verifications', 'status')) {
            Schema::table('email_verifications', function (Blueprint $table) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            });
        }

        $this->dropStaffForeignKey('phone_verifications');
        $this->dropStaffForeignKey('email_verifications');
    }

    private function retargetVerifiedByToStaff(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'verified_by')) {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_verified_by_foreign");

        if (Schema::hasTable('staff')) {
            DB::statement("
                UPDATE {$table}
                SET verified_by = NULL
                WHERE verified_by IS NOT NULL
                AND verified_by NOT IN (SELECT id FROM staff)
            ");
        }

        $hasConstraint = DB::selectOne("
            SELECT 1 FROM information_schema.table_constraints
            WHERE table_schema = 'public' AND table_name = ?
            AND constraint_type = 'FOREIGN KEY'
            AND constraint_name LIKE '%verified_by%'
        ", [$table]);

        if (! $hasConstraint && Schema::hasTable('staff')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('verified_by')
                    ->references('id')
                    ->on('staff')
                    ->onDelete('set null');
            });
        }
    }

    private function addStaffForeignKey(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'verified_by') || ! Schema::hasTable('staff')) {
            return;
        }

        DB::statement("
            UPDATE {$table}
            SET verified_by = NULL
            WHERE verified_by IS NOT NULL
            AND verified_by NOT IN (SELECT id FROM staff)
        ");

        $hasConstraint = DB::selectOne("
            SELECT 1 FROM information_schema.table_constraints
            WHERE table_schema = 'public' AND table_name = ?
            AND constraint_type = 'FOREIGN KEY'
            AND constraint_name LIKE '%verified_by%'
        ", [$table]);

        if (! $hasConstraint) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('verified_by')
                    ->references('id')
                    ->on('staff')
                    ->onDelete('set null');
            });
        }
    }

    private function dropStaffForeignKey(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'verified_by')) {
            return;
        }

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_verified_by_foreign");
    }
};
