<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the staff table with columns copied from admins (staff-specific only).
     * Staff are CRM users (FK to user_roles). Clients/leads remain in admins (identified by type).
     */
    public function up(): void
    {
        if (Schema::hasTable('staff')) {
            return;
        }

        Schema::create('staff', function (Blueprint $table) {
            $table->id();

            // Core identity
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');

            // Contact
            $table->string('country_code', 20)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('telephone', 100)->nullable();

            // Profile
            $table->string('profile_img', 500)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('verified')->default(0);

            // Staff-specific (AdminConsole)
            $table->integer('role')->nullable();
            $table->string('position')->nullable();
            $table->string('team')->nullable();
            $table->text('permission')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->tinyInteger('show_dashboard_per')->default(0);
            $table->string('time_zone', 50)->nullable();

            // Migration agent
            $table->tinyInteger('is_migration_agent')->default(0);
            $table->string('marn_number', 100)->nullable();
            $table->string('legal_practitioner_number', 100)->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_website', 500)->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_phone', 100)->nullable();
            $table->string('business_mobile', 100)->nullable();
            $table->string('business_email')->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->string('ABN_number', 100)->nullable();

            // Archive
            $table->tinyInteger('is_archived')->default(0);
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamp('archived_on')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->foreign('office_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('role')->references('id')->on('user_roles')->onDelete('set null');
        });

        // Self-referential FK for archived_by (staff table must exist first)
        Schema::table('staff', function (Blueprint $table) {
            $table->foreign('archived_by')->references('id')->on('staff')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
        });
        Schema::dropIfExists('staff');
    }
};
