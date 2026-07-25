<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureActivity extends Model
{
    protected $table = 'signature_activities';

    protected $fillable = [
        'document_id',
        'signer_id',
        'created_by',
        'actor_type',
        'action_type',
        'note',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Signature audit records are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Signature audit records are immutable and cannot be deleted.');
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(Signer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function scopeForDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    public function scopeByAction($query, $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    public function getActionTextAttribute(): string
    {
        return match ($this->action_type) {
            'created' => 'Document created',
            'fields_placed' => 'Signature fields placed',
            'sent' => 'Document sent',
            'associated' => 'Associated document',
            'detached' => 'Detached document',
            'status_changed' => 'Status changed',
            'signed' => 'Document signed',
            'link_opened' => 'Signing link opened',
            'link_viewed' => 'Signing page viewed',
            'reminder_sent' => 'Reminder sent',
            'email_sent' => 'Email sent',
            'email_failed' => 'Email failed',
            'email_delivered' => 'Email delivered',
            'stamp_failed' => 'Signature stamp failed',
            'signature_cancelled' => 'Signature cancelled',
            'voided' => 'Document voided',
            'archived' => 'Document archived',
            'downloaded_signed' => 'Signed PDF downloaded',
            'downloaded_certificate' => 'Certificate downloaded',
            default => ucfirst(str_replace('_', ' ', (string) $this->action_type)),
        };
    }
}
