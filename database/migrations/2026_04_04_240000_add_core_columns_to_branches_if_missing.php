<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stub `branches` (2025_09_01) only had id + timestamps; App\Models\Branch expects office_name and related columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE branches ADD COLUMN IF NOT EXISTS office_name VARCHAR(255) NOT NULL DEFAULT ''");
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS city VARCHAR(255) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS state VARCHAR(255) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS zip VARCHAR(32) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS country VARCHAR(255) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS phone VARCHAR(64) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS mobile VARCHAR(64) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS contact_person VARCHAR(255) NULL');
            DB::statement('ALTER TABLE branches ADD COLUMN IF NOT EXISTS choose_admin VARCHAR(255) NULL');

            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'office_name')) {
                $table->string('office_name')->default('');
            }
        });
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'address')) {
                $table->string('address')->nullable();
            }
            if (! Schema::hasColumn('branches', 'city')) {
                $table->string('city')->nullable();
            }
            if (! Schema::hasColumn('branches', 'state')) {
                $table->string('state')->nullable();
            }
            if (! Schema::hasColumn('branches', 'zip')) {
                $table->string('zip', 32)->nullable();
            }
            if (! Schema::hasColumn('branches', 'country')) {
                $table->string('country')->nullable();
            }
            if (! Schema::hasColumn('branches', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('branches', 'phone')) {
                $table->string('phone', 64)->nullable();
            }
            if (! Schema::hasColumn('branches', 'mobile')) {
                $table->string('mobile', 64)->nullable();
            }
            if (! Schema::hasColumn('branches', 'contact_person')) {
                $table->string('contact_person')->nullable();
            }
            if (! Schema::hasColumn('branches', 'choose_admin')) {
                $table->string('choose_admin')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};
