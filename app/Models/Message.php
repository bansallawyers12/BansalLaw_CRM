<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\WorkflowStage;

class Message extends Model
{
    use HasFactory;

    protected $table = 'messages';

    protected $fillable = [
        'subject',
        'message',
        'sender',
        'recipient',
        'sender_id',
        'recipient_id',
        'sent_at',
        'read_at',
        'is_read',
        'message_type',
        'client_matter_id',
        'client_matter_stage_id',
        'attachments',
        'metadata'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'is_read' => 'boolean',
        'attachments' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the sender admin user
     */
    public function sender()
    {
        return $this->belongsTo('App\\Models\\Admin', 'sender_id')->withDefault();
    }

    /**
     * Get the recipient admin user
     */
    public function recipient()
    {
        return $this->belongsTo('App\\Models\\Admin', 'recipient_id')->withDefault();
    }

    /**
     * Get the client matter
     */
    public function clientMatter()
    {
        return $this->belongsTo(\App\Models\ClientMatter::class, 'client_matter_id')->withDefault();
    }

    /**
     * Get the workflow stage
     */
    public function workflowStage()
    {
        return $this->belongsTo(\App\Models\WorkflowStage::class, 'client_matter_stage_id')->withDefault();
    }

    /**
     * Scope for unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read messages
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for messages by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('message_type', $type);
    }

    /**
     * Scope for urgent messages
     */
    public function scopeUrgent($query)
    {
        return $query->where('message_type', 'urgent');
    }

    /**
     * Scope for important messages
     */
    public function scopeImportant($query)
    {
        return $query->where('message_type', 'important');
    }

    /**
     * Scope for messages sent by a user
     */
    public function scopeSentBy($query, $userId)
    {
        return $query->where('sender_id', $userId);
    }

    /**
     * Scope for messages received by a user
     */
    public function scopeReceivedBy($query, $userId)
    {
        return $query->where('recipient_id', $userId);
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Mark message as unread
     */
    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null
        ]);
    }

    /**
     * Get formatted message type
     */
    public function getFormattedMessageTypeAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->message_type));
    }

    /**
     * Get message priority level (1-4)
     */
    public function getPriorityLevelAttribute()
    {
        return match($this->message_type) {
            'urgent' => 4,
            'important' => 3,
            'normal' => 2,
            'low_priority' => 1,
            default => 2
        };
    }

    /**
     * Check if message has attachments
     */
    public function hasAttachments()
    {
        return !empty($this->attachments) && is_array($this->attachments) && count($this->attachments) > 0;
    }

    /**
     * Get attachment count
     */
    public function getAttachmentCountAttribute()
    {
        return $this->hasAttachments() ? count($this->attachments) : 0;
    }

    /**
     * Get time since sent
     */
    public function getTimeSinceAttribute()
    {
        return $this->sent_at ? $this->sent_at->diffForHumans() : null;
    }

    /**
     * Get time since read
     */
    public function getTimeSinceReadAttribute()
    {
        return $this->read_at ? $this->read_at->diffForHumans() : null;
    }
}
