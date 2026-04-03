<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class SmsLog extends Model
{
    use Sortable;

    protected $fillable = [
        'client_id',
        'client_contact_id',
        'sender_id',
        'recipient_phone',
        'country_code',
        'formatted_phone',
        'message_content',
        'message_type',
        'template_id',
        'provider',
        'provider_message_id',
        'status',
        'error_message',
        'cost',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cost' => 'decimal:4',
    ];

    public $sortable = [
        'id',
        'client_id',
        'sender_id',
        'status',
        'provider',
        'message_type',
        'sent_at',
        'created_at',
    ];

    /**
     * Get the client who received the SMS
     */
    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the admin user who sent the SMS
     */
    public function sender()
    {
        return $this->belongsTo(Staff::class, 'sender_id');
    }

    /**
     * Get the contact record
     */
    public function contact()
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id');
    }

    /**
     * Get the template used (if any)
     */
    public function template()
    {
        return $this->belongsTo(SmsTemplate::class, 'template_id');
    }

    /**
     * Get the activity log entry
     */
    public function activity()
    {
        return $this->hasOne(ActivitiesLog::class, 'sms_log_id');
    }

    /**
     * Scope: Filter by client
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope: Filter by sender
     */
    public function scopeBySender($query, $senderId)
    {
        return $query->where('sender_id', $senderId);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by provider
     */
    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope: Filter by message type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('message_type', $type);
    }

    /**
     * Scope: Sent SMS only
     */
    public function scopeSent($query)
    {
        return $query->whereIn('status', ['sent', 'delivered']);
    }

    /**
     * Scope: Failed SMS only
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Today's SMS
     */
    public function scopeToday($query)
    {
        return $query->whereDate('sent_at', today());
    }

    /**
     * Scope: This month's SMS
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('sent_at', now()->month)
                     ->whereYear('sent_at', now()->year);
    }

    /**
     * Scope: Date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('sent_at', [$startDate, $endDate]);
    }

    /**
     * Check if SMS was delivered
     */
    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if SMS failed
     */
    public function isFailed()
    {
        return $this->status === 'failed';
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'delivered' => 'success',
            'sent' => 'info',
            'failed' => 'danger',
            'pending' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Get provider badge color
     */
    public function getProviderBadgeAttribute()
    {
        return match($this->provider) {
            'cellcast' => 'primary',
            'twilio' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get message preview (first 50 characters)
     */
    public function getMessagePreviewAttribute()
    {
        return \Illuminate\Support\Str::limit($this->message_content, 50);
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute()
    {
        return '$' . number_format($this->cost, 2);
    }
}

