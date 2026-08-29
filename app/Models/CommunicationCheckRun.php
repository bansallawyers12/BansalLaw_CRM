<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationCheckRun extends Model
{
    protected $table = 'communication_check_runs';

    protected $fillable = [
        'user_id',
        'batch_token',
        'lookback_days',
        'file_count',
        'extracted',
        'results',
        'queried',
        'storage_path',
    ];

    protected $casts = [
        'extracted' => 'array',
        'results' => 'array',
        'queried' => 'array',
        'lookback_days' => 'integer',
        'file_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }
}
