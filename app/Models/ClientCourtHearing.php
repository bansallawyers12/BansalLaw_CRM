<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ClientCourtHearing extends Model
{
    protected $table = 'client_court_hearings';

    protected $fillable = [
        'client_id',
        'client_matter_id',
        'court_name',
        'case_number',
        'judge_name',
        'hearing_date',
        'hearing_time',
        'hearing_type',
        'notes',
        'status',
        'reminder_minutes',
        'reminder_sms_sent_at',
    ];

    protected $casts = [
        'hearing_date' => 'date',
        'reminder_minutes' => 'integer',
        'reminder_sms_sent_at' => 'datetime',
    ];

    public function hearingStartsAt(): Carbon
    {
        $tz = config('app.timezone');
        $date = $this->hearing_date instanceof Carbon
            ? $this->hearing_date->copy()
            : Carbon::parse($this->hearing_date, $tz);

        if ($this->hearing_time) {
            $timeStr = $this->hearing_time instanceof \DateTimeInterface
                ? $this->hearing_time->format('H:i:s')
                : (string) $this->hearing_time;

            return Carbon::parse($date->format('Y-m-d') . ' ' . $timeStr, $tz);
        }

        return $date->copy()->startOfDay()->setTime(9, 0);
    }

    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function matter()
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }
}
