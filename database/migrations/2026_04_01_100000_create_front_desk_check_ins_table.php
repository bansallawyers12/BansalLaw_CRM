<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('front_desk_check_ins', function (Blueprint $table) {
            $table->id();

            // Staff member who processed the check-in
            $table->unsignedBigInteger('admin_id')->comment('Staff ID (staff table)');

            // Contact info collected at desk
            $table->string('phone_normalized', 30);
            $table->string('email', 255)->nullable();

            // Matched CRM record (nullable = walk-in)
            $table->unsignedBigInteger('client_id')->nullable()->comment('admins.id where type=client');
            $table->unsignedBigInteger('lead_id')->nullable()->comment('admins.id where type=lead');

            // Appointment linkage
            $table->unsignedBigInteger('appointment_id')->nullable()->comment('booking_appointments.id');
            $table->boolean('claimed_appointment')->default(false);

            // Visit reason
            $table->string('visit_reason', 100)->nullable();
            $table->text('visit_notes')->nullable();

            // Notification tracking
            $table->unsignedBigInteger('notified_staff_id')->nullable();
            $table->timestamp('notified_at')->nullable();

            // Snapshot / extra context
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for reporting
            $table->index('created_at');
            $table->index('client_id');
            $table->index('lead_id');
            $table->index('admin_id');
            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_desk_check_ins');
    }
};
