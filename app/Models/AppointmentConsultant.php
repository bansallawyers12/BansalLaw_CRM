<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AppointmentConsultant extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'appointment_consultants';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'calendar_type',
        'location',
        'specializations',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'specializations' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all appointments for this consultant.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(BookingAppointment::class, 'consultant_id');
    }

    /**
     * Get upcoming appointments for this consultant.
     */
    public function upcomingAppointments(): HasMany
    {
        return $this->hasMany(BookingAppointment::class, 'consultant_id')
            ->where('appointment_datetime', '>=', now())
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            ->orderBy('appointment_datetime');
    }

    /**
     * Scope: Active consultants only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By calendar type
     */
    public function scopeByCalendarType($query, string $type)
    {
        return $query->where('calendar_type', $type);
    }

    /**
     * Scope: Melbourne consultants
     */
    public function scopeMelbourne($query)
    {
        return $query->where('location', 'melbourne');
    }

    /**
     * Get calendar type display name
     */
    protected function calendarTypeDisplay(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->calendar_type) {
                'ajay' => 'Ajay',
                'kunal' => 'Michael',
                default => ucfirst((string) $this->calendar_type),
            }
        );
    }

    /**
     * Get location display name
     */
    protected function locationDisplay(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->location) {
                'melbourne' => 'Melbourne',
                default => ucfirst((string) $this->location),
            }
        );
    }

    /**
     * Check if consultant handles specific NOE ID
     */
    public function handlesNoeId(int $noeId): bool
    {
        return in_array($noeId, $this->specializations ?? [], true);
    }

    /**
     * Get appointments count for today (method, not accessor to avoid N+1)
     */
    public function getTodayAppointmentsCount(): int
    {
        return $this->appointments()
            ->whereDate('appointment_datetime', today())
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->count();
    }

    /**
     * Get upcoming appointments count (method, not accessor to avoid N+1)
     */
    public function getUpcomingAppointmentsCount(): int
    {
        return $this->appointments()
            ->where('appointment_datetime', '>=', now())
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            ->count();
    }

    /**
     * Get specialization names (practice-area NOEs 1–7).
     */
    public function getSpecializationNames(): array
    {
        $labels = [];
        foreach (config('booking_nature_of_enquiry.crm', []) as $row) {
            $labels[(int) $row['id']] = (string) $row['label'];
        }

        return array_map(
            fn ($noeId) => $labels[(int) $noeId] ?? ('Service ' . $noeId),
            $this->specializations ?? []
        );
    }
}
