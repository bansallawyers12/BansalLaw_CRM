<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_appointments', function (Blueprint $table) {
            $table->id();
            
            // External Reference (Bansal Website)
            $table->unsignedBigInteger('bansal_appointment_id')->unique()->comment('ID from Bansal website');
            $table->string('order_hash')->nullable()->comment('Payment order hash from Bansal');
            
            // Relationships
            $table->unsignedInteger('client_id')->nullable()->comment('FK to admins.id (clients/leads)');
            $table->unsignedBigInteger('consultant_id')->nullable()->comment('FK to appointment_consultants.id');
            $table->unsignedInteger('assigned_by_admin_id')->nullable()->comment('Admin who assigned consultant');
            
            // Client Information (from Bansal API)
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone', 50)->nullable();
            $table->string('client_timezone', 50)->default('Australia/Melbourne');
            
            // Appointment Details
            $table->dateTime('appointment_datetime');
            $table->string('timeslot_full', 50)->nullable()->comment('e.g., "9:00 AM - 9:15 AM"');
            $table->integer('duration_minutes')->default(15);
            $table->enum('location', ['melbourne', 'adelaide']);
            $table->tinyInteger('inperson_address')->nullable()->comment('Legacy: 1=Adelaide, 2=Melbourne');
            $table->enum('meeting_type', ['in_person', 'phone', 'video'])->default('in_person');
            $table->string('preferred_language', 50)->default('English');
            
            // Service Details (from Bansal)
            $table->tinyInteger('service_id')->nullable()->comment('Legacy: 1=Paid, 2=Free');
            $table->tinyInteger('noe_id')->nullable()->comment('Legacy: Nature of Enquiry ID');
            $table->string('enquiry_type', 100)->nullable()->comment('tr, tourist, education, etc.');
            $table->string('service_type', 100)->nullable()->comment('Display name');
            $table->text('enquiry_details')->nullable();
            
            // Status & Lifecycle
            $table->enum('status', ['pending', 'paid', 'confirmed', 'completed', 'cancelled', 'no_show', 'rescheduled'])->default('pending');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Payment Info (from Bansal, read-only)
            $table->boolean('is_paid')->default(false);
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2)->default(0);
            $table->string('promo_code', 50)->nullable();
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->dateTime('paid_at')->nullable();
            
            // CRM-specific Fields (editable by staff)
            $table->text('admin_notes')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            
            // Notification Tracking
            $table->boolean('confirmation_email_sent')->default(false);
            $table->dateTime('confirmation_email_sent_at')->nullable();
            $table->boolean('reminder_sms_sent')->default(false);
            $table->dateTime('reminder_sms_sent_at')->nullable();
            
            // Sync Metadata
            $table->dateTime('synced_from_bansal_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->enum('sync_status', ['new', 'synced', 'error'])->default('new');
            $table->text('sync_error')->nullable();
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('client_id')->references('id')->on('admins')->onDelete('set null');
            $table->foreign('consultant_id')->references('id')->on('appointment_consultants')->onDelete('set null');
            $table->foreign('assigned_by_admin_id')->references('id')->on('admins')->onDelete('set null');
            
            // Indexes
            $table->index('client_id');
            $table->index('consultant_id');
            $table->index('appointment_datetime');
            $table->index('status');
            $table->index('location');
            $table->index(['service_id', 'noe_id']);
            $table->index('sync_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_appointments');
    }
};

