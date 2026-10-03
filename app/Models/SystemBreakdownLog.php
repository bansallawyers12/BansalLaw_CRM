<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class SystemBreakdownLog extends Model
{
    use HasFactory;

    protected $table = 'system_breakdown_logs';

    protected $fillable = [
        'error_hash',
        'exception_class',
        'message',
        'file',
        'line',
        'url',
        'http_method',
        'status_code',
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'ip_address',
        'user_agent',
        'request_payload',
        'request_headers',
        'stack_trace',
        'occurrence_count',
        'status',
        'resolution_notes',
        'resolved_at',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'request_headers' => 'array',
        'occurrence_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        $clean = trim($term);
        return $query->where(function ($q) use ($clean) {
            $q->where('message', 'like', "%{$clean}%")
              ->orWhere('exception_class', 'like', "%{$clean}%")
              ->orWhere('file', 'like', "%{$clean}%")
              ->orWhere('url', 'like', "%{$clean}%")
              ->orWhere('user_email', 'like', "%{$clean}%")
              ->orWhere('user_name', 'like', "%{$clean}%");
        });
    }

    public function getShortFileAttribute(): string
    {
        if (empty($this->file)) {
            return 'Unknown source';
        }
        $parts = explode(DIRECTORY_SEPARATOR, str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->file));
        $len = count($parts);
        if ($len > 3) {
            return '...' . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, array_slice($parts, -3)) . ($this->line ? ':' . $this->line : '');
        }
        return $this->file . ($this->line ? ':' . $this->line : '');
    }

    public function getRelativeLastSeenAttribute(): string
    {
        return $this->last_seen_at ? $this->last_seen_at->diffForHumans() : 'Just now';
    }
}
