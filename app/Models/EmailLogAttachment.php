<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailLogAttachment extends Model
{
    use HasFactory;

    protected $table = 'email_log_attachments';

    protected $fillable = [
        'email_log_id',
        'filename',
        'display_name',
        'content_type',
        'file_path',
        's3_key',
        'file_size',
        'content_id',
        'is_inline',
        'description',
        'headers',
        'extension',
    ];

    protected $casts = [
        'is_inline' => 'boolean',
        'headers' => 'array',
    ];

    /**
     * Get the email log that owns the attachment.
     */
    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the display name or filename.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name'] ?? $this->filename;
    }

    /**
     * Resolve a usable MIME type (Outlook uploads often store application/octet-stream).
     */
    public function resolveContentType(): string
    {
        $type = strtolower(trim((string) $this->content_type));

        if ($type !== '' && $type !== 'application/octet-stream') {
            return $this->content_type;
        }

        $extension = strtolower((string) ($this->extension ?: pathinfo((string) $this->filename, PATHINFO_EXTENSION)));

        $map = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
        ];

        return $map[$extension] ?? ($this->content_type ?: 'application/octet-stream');
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        $type = strtolower($this->resolveContentType());
        if (str_starts_with($type, 'image/')) {
            return true;
        }

        $extension = strtolower((string) ($this->extension ?: pathinfo((string) $this->filename, PATHINFO_EXTENSION)));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true);
    }

    /**
     * Check if the attachment is a PDF.
     */
    public function isPdf(): bool
    {
        $type = strtolower($this->resolveContentType());

        return str_contains($type, 'pdf')
            || strtolower((string) ($this->extension ?: pathinfo((string) $this->filename, PATHINFO_EXTENSION))) === 'pdf';
    }

    /**
     * Check if the attachment is a document.
     */
    public function isDocument(): bool
    {
        $documentTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/rtf',
            'text/html',
            'text/css',
            'application/json',
            'application/xml',
            'text/csv',
        ];
        return in_array($this->content_type, $documentTypes);
    }

    /**
     * Check if the attachment can be previewed.
     */
    public function canPreview(): bool
    {
        if ($this->isImage() || $this->isPdf()) {
            return true;
        }

        $name = strtolower((string) ($this->filename ?? ''));

        return (bool) preg_match('/\.(pdf|png|jpe?g|gif|webp|bmp)$/i', $name);
    }

    /**
     * Get the icon class for the attachment type.
     */
    public function getIconClassAttribute(): string
    {
        if ($this->isImage()) {
            return 'fa-solid fa-image text-blue-500';
        }
        
        if ($this->isPdf()) {
            return 'fa-solid fa-file-pdf text-red-500';
        }
        
        if ($this->isDocument()) {
            return 'fa-solid fa-file-alt text-gray-500';
        }
        
        return 'fa-solid fa-paperclip text-gray-400';
    }

    /**
     * Scope to filter by content type.
     */
    public function scopeOfType($query, $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope to filter inline attachments.
     */
    public function scopeInline($query)
    {
        return $query->where('is_inline', true);
    }

    /**
     * Scope to filter regular attachments.
     */
    public function scopeRegular($query)
    {
        return $query->where('is_inline', false);
    }
}
