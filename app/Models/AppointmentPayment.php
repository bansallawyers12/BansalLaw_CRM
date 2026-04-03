<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentPayment extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'appointment_payments';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'appointment_id',
        'payment_gateway',
        'transaction_id',
        'charge_id',
        'customer_id',
        'payment_method_id',
        'amount',
        'currency',
        'status',
        'error_message',
        'transaction_data',
        'receipt_url',
        'refund_amount',
        'refunded_at',
        'client_ip',
        'user_agent',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'transaction_data' => 'array',
            'processed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * Get the appointment that owns the payment.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(BookingAppointment::class, 'appointment_id');
    }

    /**
     * Scope: Successful payments
     */
    public function scopeSucceeded($query)
    {
        return $query->where('status', 'succeeded');
    }

    /**
     * Scope: Failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Stripe payments
     */
    public function scopeStripe($query)
    {
        return $query->where('payment_gateway', 'stripe');
    }

    /**
     * Check if payment is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }

    /**
     * Check if payment failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Check if payment is refunded
     */
    public function isRefunded(): bool
    {
        return in_array($this->status, ['refunded', 'partially_refunded']);
    }
}
