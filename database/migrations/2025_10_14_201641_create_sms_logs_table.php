<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            
            // Client and contact references
            $table->unsignedBigInteger('client_id')->nullable()->comment('Client who received SMS');
            $table->unsignedBigInteger('client_contact_id')->nullable()->comment('Specific contact record');
            $table->unsignedBigInteger('sender_id')->nullable()->comment('Admin user who sent SMS');
            
            // Phone details
            $table->string('recipient_phone', 20)->comment('Original phone number entered');
            $table->string('country_code', 10)->default('+61')->comment('Country code');
            $table->string('formatted_phone', 25)->nullable()->comment('Final formatted number sent to provider');
            
            // Message details
            $table->text('message_content')->comment('Full SMS message content');
            $table->enum('message_type', ['verification', 'notification', 'manual', 'reminder'])
                  ->default('manual')
                  ->comment('Type of SMS message');
            $table->unsignedBigInteger('template_id')->nullable()->comment('Template used if applicable');
            
            // Provider details
            $table->string('provider', 20)->comment('cellcast or twilio');
            $table->string('provider_message_id', 100)->nullable()->comment('Message ID from provider (SID)');
            
            // Status tracking
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])
                  ->default('pending')
                  ->comment('Delivery status');
            $table->text('error_message')->nullable()->comment('Error details if failed');
            
            // Cost tracking
            $table->decimal('cost', 10, 4)->nullable()->default(0)->comment('SMS cost');
            
            // Timestamps
            $table->timestamp('sent_at')->nullable()->comment('When SMS was sent to provider');
            $table->timestamp('delivered_at')->nullable()->comment('When SMS was delivered');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('client_id');
            $table->index('sender_id');
            $table->index('client_contact_id');
            $table->index('status');
            $table->index('provider');
            $table->index('message_type');
            $table->index('sent_at');
            $table->index(['client_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_logs');
    }
};
