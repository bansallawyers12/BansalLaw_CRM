<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Authenticatable
{
    use Notifiable;
	use Sortable;

    public const SEND_STATUS_PENDING = 'pending';

    public const SEND_STATUS_SENT = 'sent';

    public const SEND_STATUS_FAILED = 'failed';

    public const SYNC_SOURCE_MANUAL = 'manual';

    public const SYNC_SOURCE_CRON = 'cron';

    public const SYNC_SOURCE_COMPOSE = 'compose';

    public static function syncSourceLabel(?string $source): string
    {
        return match ($source) {
            self::SYNC_SOURCE_MANUAL => 'Manual sync',
            self::SYNC_SOURCE_CRON => 'Auto fetch',
            self::SYNC_SOURCE_COMPOSE => 'CRM sent',
            default => '',
        };
    }

	protected $table = 'email_logs';

	protected $fillable = [
        'id',
        'user_id',
        'from_mail',
        'mailbox_email',
        'synced_email_id',
        'sync_assignment_status',
        'imap_uid',
        'sync_source',
        'to_mail',
        'cc',
        'bcc',
        'template_id',
        'subject',
        'message',
        'type',
        'reciept_id',
        'attachments',
        'mail_type',
        'mail_body_type',
        'send_status',
        'send_error',
        'sent_at',
        'failed_at',
        'retry_count',
        'resend_of_id',
        'client_id',
        'client_matter_id',
        'conversion_type',
        'mail_body_type',
        'fetch_mail_sent_time',
        'uploaded_doc_id',
        'pdf_doc_id',
        'mail_is_read',
        // Python analysis fields
        'python_analysis',
        'python_rendering',
        'sentiment',
        'language',
        'enhanced_html',
        'rendered_html',
        'text_preview',
        'security_issues',
        'thread_info',
        'processed_at',
        // Additional metadata
        'message_id',
        'thread_id',
        'received_date',
        'last_accessed_at',
        'file_hash',
        'created_at',
        'updated_at'
    ];

	public $sortable = ['id', 'created_at', 'updated_at', 'subject', 'from_mail'];

    protected $casts = [
        'python_analysis' => 'array',
        'python_rendering' => 'array',
        'security_issues' => 'array',
        'thread_info' => 'array',
        'processed_at' => 'datetime',
        'fetch_mail_sent_time' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'received_date' => 'datetime',
        'last_accessed_at' => 'datetime',
        'mail_is_read' => 'boolean',
    ];

    /**
     * Get the attachments for the email.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailLogAttachment::class, 'email_log_id');
    }

    public function calendarLinks(): HasMany
    {
        return $this->hasMany(EmailCalendarLink::class, 'email_log_id');
    }

    /**
     * Get the labels for the email.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(EmailLabel::class, 'email_label_email_log', 'email_log_id', 'email_label_id')
                    ->withTimestamps();
    }

    /**
     * Get the client that owns the email.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the client matter linked to the email.
     */
    public function matter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    /**
     * Get the user who uploaded the email.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * Check if the email has attachments.
     */
    public function hasAttachments(): bool
    {
        return $this->attachments()->count() > 0;
    }

    /**
     * Get the number of attachments.
     */
    public function getAttachmentCountAttribute(): int
    {
        return $this->attachments()->count();
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!isset($this->attributes['file_size'])) {
            return '0 B';
        }
        
        $bytes = $this->attributes['file_size'];
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if email has Python analysis.
     */
    public function hasPythonAnalysis(): bool
    {
        return !empty($this->python_analysis);
    }

    /**
     * Check if email has security issues.
     */
    public function hasSecurityIssues(): bool
    {
        return !empty($this->security_issues);
    }

    /**
     * Check if email is a reply.
     */
    public function isReply(): bool
    {
        return isset($this->thread_info['is_reply']) && $this->thread_info['is_reply'];
    }

    /**
     * Scope to filter by sentiment.
     */
    public function scopeBySentiment($query, $sentiment)
    {
        return $query->where('sentiment', $sentiment);
    }

    /**
     * Scope to search emails.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('subject', 'like', "%{$search}%")
              ->orWhere('from_mail', 'like', "%{$search}%")
              ->orWhere('to_mail', 'like', "%{$search}%")
              ->orWhere('text_preview', 'like', "%{$search}%");
        });
    }

    /**
     * The PDF document generated from this uploaded email.
     */
    public function pdfDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'pdf_doc_id');
    }

    /**
     * Scope to filter by label.
     */
    public function scopeWithLabel($query, $labelId)
    {
        return $query->whereHas('labels', function ($q) use ($labelId) {
            $q->where('email_labels.id', $labelId);
        });
    }

    /**
     * Scope to filter emails with attachments.
     */
    public function scopeWithAttachments($query)
    {
        return $query->has('attachments');
    }

    /**
     * Scope to filter emails without attachments.
     */
    public function scopeWithoutAttachments($query)
    {
        return $query->doesntHave('attachments');
    }

    /**
     * CRM compose stores recipient Admin/Agent row IDs in to_mail; Sent tab should show addresses.
     * Resolves comma-separated numeric IDs to emails. Leaves real addresses unchanged.
     */
    public static function resolveRecipientDisplay(?string $toMail, ?string $logType = null): string
    {
        if ($toMail === null || ($toMail = trim($toMail)) === '') {
            return '';
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $toMail)), static fn ($p) => $p !== ''));
        if ($parts === []) {
            return $toMail;
        }
        $allEmails = true;
        foreach ($parts as $p) {
            if (! filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $allEmails = false;
                break;
            }
        }
        if ($allEmails) {
            return $toMail;
        }
        foreach ($parts as $p) {
            if (! ctype_digit((string) $p)) {
                return $toMail;
            }
        }
        $emails = [];
        $isAgent = ($logType === 'agent');
        foreach ($parts as $idStr) {
            $idInt = (int) $idStr;
            if ($isAgent) {
                $agent = AgentDetails::find($idInt);
                $em = $agent ? ($agent->email ?: $agent->business_email) : null;
                if ($em) {
                    $emails[] = $em;
                }
            } else {
                $admin = Admin::find($idInt);
                if ($admin && ! empty($admin->email)) {
                    $emails[] = $admin->email;
                }
            }
        }

        return $emails !== [] ? implode(', ', array_unique($emails)) : $toMail;
    }
}
