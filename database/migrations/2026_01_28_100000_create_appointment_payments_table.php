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
        Schema::create('appointment_payments', function (Blueprint $table) {
            $table->id();
            
            // Appointment Reference
            $table->unsignedBigInteger('appointment_id')->comment('FK to booking_appointments.id');
            
            // Payment Gateway Info
            $table->enum('payment_gateway', ['stripe', 'paypal', 'manual'])->default('stripe');
            
            // Stripe Transaction Details
            $table->string('transaction_id')->nullable()->comment('Stripe PaymentIntent ID (pi_xxx)');
            $table->string('charge_id')->nullable()->comment('Stripe Charge ID (ch_xxx)');
            $table->string('customer_id')->nullable()->comment('Stripe Customer ID (cus_xxx)');
            $table->string('payment_method_id')->nullable()->comment('Stripe Payment Method ID (pm_xxx)');
            
            // Payment Details
            $table->decimal('amount', 10, 2)->comment('Payment amount');
            $table->string('currency', 3)->default('AUD')->comment('Currency code');
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            
            // Additional Info
            $table->text('error_message')->nullable()->comment('Error message if payment failed');
            $table->json('transaction_data')->nullable()->comment('Full Stripe response JSON');
            $table->string('receipt_url')->nullable()->comment('Stripe receipt URL');
            
            // Refund Info (for future use)
            $table->decimal('refund_amount', 10, 2)->default(0)->comment('Total refunded amount');
            $table->dateTime('refunded_at')->nullable();
            
            // Metadata
            $table->string('client_ip', 45)->nullable()->comment('Client IP address');
            $table->text('user_agent')->nullable()->comment('Client user agent');
            $table->dateTime('processed_at')->nullable()->comment('When payment was processed');
            
            $table->timestamps();
            
            // Foreign Keys
            // Note: We're not adding foreign key constraint here to avoid PostgreSQL issues
            // The relationship is managed at the application level via the AppointmentPayment model
            
            // Indexes
            $table->index('appointment_id');
            $table->index('transaction_id');
            $table->index('charge_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('payment_gateway');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_payments');
    }
};
