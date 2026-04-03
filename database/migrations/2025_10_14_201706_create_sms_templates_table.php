<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            
            // Template details (following EmailTemplate pattern)
            $table->string('title', 100)->comment('Template name');
            $table->text('message')->comment('SMS message content with variables');
            $table->text('variables')->nullable()->comment('Comma-separated list of variables');
            $table->string('category', 50)->nullable()->comment('verification, reminder, notification, manual');
            $table->string('alias', 50)->nullable()->unique()->comment('Unique identifier for programmatic access');
            
            // Status and usage
            $table->boolean('is_active')->default(true)->comment('Whether template is active');
            $table->integer('usage_count')->default(0)->comment('Number of times template has been used');
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable()->comment('Admin user who created template');
            $table->timestamps();
            
            // Indexes
            $table->index('category');
            $table->index('is_active');
            $table->index(['is_active', 'category']);
        });

        // Seed default templates
        DB::table('sms_templates')->insert([
            [
                'title' => 'Appointment Reminder',
                'message' => 'Hi {first_name}, this is a reminder for your appointment on {appointment_date} at {appointment_time}. Call {office_phone} if you need to reschedule.',
                'variables' => 'first_name,appointment_date,appointment_time,office_phone',
                'category' => 'reminder',
                'alias' => 'appointment_reminder',
                'is_active' => true,
                'usage_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Document Upload Request',
                'message' => 'Hi {first_name}, please upload the requested documents for matter {matter_number}. Login to your client portal or contact {staff_name} at {office_phone}.',
                'variables' => 'first_name,matter_number,staff_name,office_phone',
                'category' => 'notification',
                'alias' => 'document_request',
                'is_active' => true,
                'usage_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Phone Verification Code',
                'message' => 'BANSAL IMMIGRATION: Your verification code is {verification_code}. This code expires in 5 minutes.',
                'variables' => 'verification_code',
                'category' => 'verification',
                'alias' => 'phone_verification',
                'is_active' => true,
                'usage_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'General Follow-up',
                'message' => 'Hi {client_name}, {staff_name} from Bansal Immigration. Please call us at {office_phone} regarding your matter {matter_number}.',
                'variables' => 'client_name,staff_name,office_phone,matter_number',
                'category' => 'manual',
                'alias' => 'general_followup',
                'is_active' => true,
                'usage_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Payment Reminder',
                'message' => 'Hi {first_name}, this is a reminder about your pending payment for invoice #{invoice_number}. Please contact us at {office_phone} or login to your portal to make payment.',
                'variables' => 'first_name,invoice_number,office_phone',
                'category' => 'reminder',
                'alias' => 'payment_reminder',
                'is_active' => true,
                'usage_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_templates');
    }
};
