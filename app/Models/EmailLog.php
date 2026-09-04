<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use App\Traits\SortableTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class EmailLog extends Authenticatable
{
    use Notifiable;
	use SortableTrait;

    public const SEND_STATUS_PENDING = 'pending';

    public const SEND_STATUS_SENT = 'sent';

    public const SEND_STATUS_FAILED = 'failed';

    public const SYNC_SOURCE_MANUAL = 'manual';

    public const SYNC_SOURCE_CRON = 'cron';

    public const SYNC_SOURCE_COMPOSE = 'compose';

    /** Staff drag/drop or file-picker upload of .msg/.eml onto a client. */
    public const SYNC_SOURCE_UPLOAD = 'upload';

    public static function syncSourceLabel(?string $source): string
    {
        return match ($source) {
            self::SYNC_SOURCE_MANUAL => 'Manual sync',
            self::SYNC_SOURCE_CRON => 'Auto fetch',
            self::SYNC_SOURCE_COMPOSE => 'CRM sent',
            self::SYNC_SOURCE_UPLOAD => 'Manual upload',
            default => '',
        };
    }

    /**
     * True when content is an ICS/calendar dump rather than a readable email body.
     */
    public static function isCalendarPayload(?string $content): bool
    {
        if ($content === null) {
            return false;
        }

        $text = ltrim($content, "\xEF\xBB\xBF");
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $upper = strtoupper($text);
        if (str_starts_with($upper, 'BEGIN:VCALENDAR')) {
            return true;
        }

        $head = substr($upper, 0, 800);

        return str_contains($head, 'BEGIN:VCALENDAR') && str_contains($upper, 'BEGIN:VEVENT');
    }

    /**
     * Outlook/Zoho calendar invite / RSVP subject prefixes (case-insensitive).
     */
    public const CALENDAR_INVITE_SUBJECT_PATTERN =
        '^(invitation|accepted|declined|tentative|canceled|cancelled|updated invitation|meeting request|meeting invitation|meeting forward notification)\\b';

    /**
     * Court / tribunal hearing notices that belong on the hearing list and calendar, not mail lists.
     */
    public const HEARING_NOTICE_SUBJECT_PATTERN =
        '\\b(hearing|tribunal|court listing|directions hearing|case management hearing|in[- ]?person hearing)\\b';

    public static function calendarInvitationBodyMarker(): string
    {
        return 'This message is a calendar invitation.';
    }

    /**
     * True when the subject looks like a calendar invite / RSVP, not a normal email.
     */
    public static function isCalendarInviteSubject(?string $subject): bool
    {
        $subject = trim((string) $subject);
        if ($subject === '') {
            return false;
        }

        return (bool) preg_match('/' . self::CALENDAR_INVITE_SUBJECT_PATTERN . '/i', $subject);
    }

    /**
     * True when the subject looks like a court/tribunal hearing notice.
     */
    public static function isHearingNoticeSubject(?string $subject): bool
    {
        $subject = trim((string) $subject);
        if ($subject === '') {
            return false;
        }

        return (bool) preg_match('/' . self::HEARING_NOTICE_SUBJECT_PATTERN . '/i', $subject);
    }

    /**
     * Exclude calendar invites / ICS events / hearing notices from mail lists only.
     * Does not delete email_logs, StaffCalendarEvent, or ClientCourtHearing records —
     * calendar and hearing lists are untouched.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyExcludeCalendarInvitesFromMailLists($query): void
    {
        $invitationPhrase = self::calendarInvitationBodyMarker();

        $query->where(function ($keep) use ($invitationPhrase) {
            $keep->whereNull('message')
                ->orWhere('message', 'not like', '%' . $invitationPhrase . '%');
        });

        $query->where(function ($keep) {
            $keep->whereNull('text_preview')
                ->orWhere('text_preview', '')
                ->orWhereRaw(
                    "UPPER(LEFT(TRIM(COALESCE(text_preview, '')), 850)) NOT LIKE ?",
                    ['%BEGIN:VCALENDAR%']
                );
        });

        // Also catch invitation text left in the lean preview column after summarizeCalendarPayload.
        $query->where(function ($keep) use ($invitationPhrase) {
            $keep->whereNull('text_preview')
                ->orWhere('text_preview', 'not like', '%' . $invitationPhrase . '%');
        });

        $query->whereRaw("COALESCE(subject, '') !~* ?", [self::CALENDAR_INVITE_SUBJECT_PATTERN]);

        // Hearing date notices belong on the hearing list / calendar only.
        // Use ILIKE (not \b) — PostgreSQL does not treat \b as a word boundary.
        $query->where(function ($keep) {
            $keep->whereRaw("LOWER(COALESCE(subject, '')) NOT LIKE ?", ['%hearing%'])
                ->whereRaw("LOWER(COALESCE(subject, '')) NOT LIKE ?", ['%tribunal%'])
                ->whereRaw("LOWER(COALESCE(subject, '')) NOT LIKE ?", ['%court listing%']);
        });

        // Any existing calendar link means this row already has a calendar/hearing event.
        if (Schema::hasTable('email_calendar_links')) {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('email_calendar_links')
                    ->whereColumn('email_calendar_links.email_log_id', 'email_logs.id');
            });
        }

        // Emails that already created a ClientCourtHearing (notes reference Email #id).
        if (Schema::hasTable('client_court_hearings')) {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('client_court_hearings')
                    ->whereRaw(
                        "client_court_hearings.notes LIKE ('%Email #' || email_logs.id::text || '%')"
                    );
            });
        }

        // ICS attachments without a link yet still belong on the calendar, not mail lists.
        if (Schema::hasTable('email_log_attachments')) {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('email_log_attachments')
                    ->whereColumn('email_log_attachments.email_log_id', 'email_logs.id')
                    ->where(function ($att) {
                        $att->whereRaw("LOWER(COALESCE(email_log_attachments.extension, '')) = 'ics'")
                            ->orWhereRaw("LOWER(COALESCE(email_log_attachments.filename, '')) LIKE '%.ics'")
                            ->orWhereRaw("LOWER(COALESCE(email_log_attachments.content_type, '')) LIKE '%calendar%'");
                    });
            });
        }
    }

    public static function icsPropertyValue(string $ics, string $field): string
    {
        $unfolded = preg_replace("/\r?\n[ \t]/", '', $ics) ?? $ics;
        $pattern = '/^' . preg_quote($field, '/') . '(?:;[^:]*)?:(.+)$/im';
        if (! preg_match($pattern, $unfolded, $matches)) {
            return '';
        }

        $value = trim((string) $matches[1]);
        $value = str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);

        return trim($value);
    }

    public static function summarizeCalendarPayload(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return 'Calendar invitation';
        }

        if (! self::isCalendarPayload($content)) {
            return 'Calendar invitation';
        }

        $summary = self::icsPropertyValue($content, 'SUMMARY') ?: 'Calendar invitation';
        $location = self::icsPropertyValue($content, 'LOCATION');
        $description = self::icsPropertyValue($content, 'DESCRIPTION');
        $dtStart = self::icsPropertyValue($content, 'DTSTART');
        $dtEnd = self::icsPropertyValue($content, 'DTEND');

        $lines = [$summary, '', 'This message is a calendar invitation.'];
        if ($dtStart !== '') {
            $lines[] = 'When: ' . $dtStart . ($dtEnd !== '' ? ' – ' . $dtEnd : '');
        }
        if ($location !== '') {
            $lines[] = 'Where: ' . $location;
        }
        if ($description !== '') {
            $desc = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $desc = preg_replace('/\s+/u', ' ', $desc) ?? $desc;
            $desc = trim((string) $desc);
            if ($desc !== '') {
                if (mb_strlen($desc) > 600) {
                    $desc = mb_substr($desc, 0, 600) . '…';
                }
                $lines[] = '';
                $lines[] = $desc;
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Plain-text list snippet from HTML email bodies.
     * Style/script blocks are removed entirely (strip_tags keeps their inner CSS).
     * Calendar invites return the meeting title instead of raw VCALENDAR text.
     */
    public static function plainTextPreview(?string $html, int $maxLen = 100): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        if (self::isCalendarPayload($html)) {
            $summary = self::icsPropertyValue($html, 'SUMMARY') ?: 'Calendar invitation';
            if ($maxLen > 0 && mb_strlen($summary) > $maxLen) {
                return mb_substr($summary, 0, $maxLen);
            }

            return $summary;
        }

        $html = preg_replace('#<(script|style|head|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|p|div|li|tr|h[1-6])\b[^>]*/?>#i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[.#]?[\w-]+\s*\{[^}]*\}/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($maxLen > 0 && mb_strlen($text) > $maxLen) {
            return mb_substr($text, 0, $maxLen);
        }

        return $text;
    }

    /**
     * Whether this log row came from IMAP/synced-inbox assignment (not a pure file upload).
     */
    public function isSyncedInboxOrigin(): bool
    {
        return ! empty($this->synced_email_id);
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
        'mail_type',
        'mail_body_type',
        'send_status',
        'send_error',
        'sent_at',
        'failed_at',
        'retry_count',
        'client_id',
        'client_matter_id',
        'conversion_type',
        'fetch_mail_sent_time',
        'uploaded_doc_id',
        'pdf_doc_id',
        'mail_is_read',
        'python_analysis',
        'sentiment',
        'language',
        'text_preview',
        'security_issues',
        'thread_info',
        'processed_at',
        'message_id',
        'received_date',
        'file_hash',
        'created_at',
        'updated_at'
    ];

	public $sortable = ['id', 'created_at', 'updated_at', 'subject', 'from_mail'];

    protected $casts = [
        'python_analysis' => 'array',
        'security_issues' => 'array',
        'thread_info' => 'array',
        'processed_at' => 'datetime',
        'fetch_mail_sent_time' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'received_date' => 'datetime',
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
                $agent = Staff::find($idInt);
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
