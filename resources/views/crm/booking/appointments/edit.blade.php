@extends('layouts.crm_client_detail')
@section('title', 'Edit Appointment - #' . $appointment->id)

@section('content')

@php
    $humanize = static function ($value, $fallback = '—') {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return $fallback;
        }
        return \Illuminate\Support\Str::of($raw)->replace(['_', '-'], ' ')->title()->toString();
    };

    $locationLabel = in_array($appointment->location, ['melbourne', 'adelaide'], true)
        ? 'Melbourne Office'
        : $humanize($appointment->location);

    $noeDisplay = $appointment->service_type
        ?: (collect(config('booking_nature_of_enquiry.crm'))->firstWhere('id', (int) $appointment->noe_id)['label'] ?? null);
    $noeDisplay = $noeDisplay ? $humanize($noeDisplay) : '—';

    $serviceTypeDisplay = match ((int) $appointment->service_id) {
        1 => 'Standard Consultation',
        2 => 'Free Consultation',
        3 => 'Extended Consultation',
        default => '—',
    };

    $syncStatus = $appointment->sync_status ?? 'new';
    $syncStatusClass = match ($syncStatus) {
        'synced' => 'success',
        'error' => 'danger',
        'new' => 'warning',
        default => 'secondary',
    };
    $syncStatusText = match ($syncStatus) {
        'synced' => 'Synced',
        'error' => 'Error',
        'new' => 'New',
        default => ucfirst((string) $syncStatus),
    };
@endphp

<style>
/* Edit appointment — Powder Blue & Soft Gold (docs/theme.md), page-scoped */
.booking-appointment-edit {
    overflow-x: hidden;
    max-width: 100%;
    padding-bottom: 2rem;
    font-family: 'Segoe UI', sans-serif;
}

.booking-appointment-edit .ba-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    padding: 12px 4px 16px;
    margin: 0 0 4px;
}

.booking-appointment-edit .ba-toolbar .btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.5rem 1rem;
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    line-height: 1.2;
}

.booking-appointment-edit .ba-toolbar .btn-secondary {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border, #c8dcef);
    color: var(--navy, #1e3d60);
}

.booking-appointment-edit .ba-toolbar .btn-secondary:hover {
    background: var(--sidebar-bg, #ddeaf8);
    color: var(--navy, #1e3d60);
}

.booking-appointment-edit > .ba-shell {
    border: 1px solid var(--border, #c8dcef);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(30, 61, 96, 0.06);
    background: var(--card-bg, #fff);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.booking-appointment-edit .ba-shell__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 16px;
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--border, #c8dcef);
}

.booking-appointment-edit .ba-shell__title {
    margin: 0;
    color: var(--navy, #1e3d60);
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.booking-appointment-edit .ba-shell__title small {
    color: var(--text-muted, #5e7a90);
    font-weight: 500;
    font-size: 0.85rem;
}

.booking-appointment-edit .ba-shell__body {
    padding: 1.15rem 1.2rem 1.5rem;
    background: var(--page-bg, #f0f6ff);
}

.booking-appointment-edit .ba-hint {
    margin: 0 0 14px;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--border, #c8dcef);
    background: rgba(58, 111, 168, 0.08);
    color: var(--navy, #1e3d60);
    font-size: 0.9rem;
    font-weight: 500;
}

.booking-appointment-edit .ba-alert {
    border-radius: 8px;
    border: 1px solid var(--border, #c8dcef);
    margin-bottom: 14px;
}

.booking-appointment-edit .ba-alert.alert-success {
    background: rgba(30, 122, 82, 0.1);
    border-color: rgba(30, 122, 82, 0.25);
    color: var(--success, #1e7a52);
}

.booking-appointment-edit .ba-alert.alert-danger {
    background: rgba(168, 48, 32, 0.08);
    border-color: rgba(168, 48, 32, 0.22);
    color: var(--danger, #a83020);
}

.booking-appointment-edit .ba-panel {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border, #c8dcef);
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.05);
    overflow: hidden;
    margin-bottom: 14px;
}

.booking-appointment-edit .ba-panel:last-child {
    margin-bottom: 0;
}

.booking-appointment-edit .ba-panel__head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border, #c8dcef);
    background: linear-gradient(180deg, #f8fbff 0%, #fff 100%);
}

.booking-appointment-edit .ba-panel__title {
    margin: 0;
    color: var(--navy, #1e3d60);
    font-size: 0.92rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.booking-appointment-edit .ba-panel__icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    background: rgba(58, 111, 168, 0.14);
    color: var(--sidebar-active, #3a6fa8);
}

.booking-appointment-edit .ba-panel__icon--navy {
    background: rgba(30, 61, 96, 0.1);
    color: var(--navy, #1e3d60);
}

.booking-appointment-edit .ba-panel__body {
    padding: 1rem;
}

.booking-appointment-edit .ba-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 16px;
}

@media (max-width: 767px) {
    .booking-appointment-edit .ba-form-grid {
        grid-template-columns: 1fr;
    }
}

.booking-appointment-edit .ba-field label {
    display: block;
    margin-bottom: 6px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-muted, #5e7a90);
}

.booking-appointment-edit .ba-field .form-control,
.booking-appointment-edit .ba-field select.form-control {
    border: 1px solid var(--border, #c8dcef);
    border-radius: 8px;
    min-height: 40px;
    color: var(--text-dark, #1a2c40);
    background: var(--card-bg, #fff);
    font-size: 0.9rem;
    max-width: none !important;
}

.booking-appointment-edit .ba-field .form-control:focus,
.booking-appointment-edit .ba-field select.form-control:focus {
    border-color: var(--sidebar-active, #3a6fa8);
    box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
}

.booking-appointment-edit .ba-field .form-text {
    margin-top: 6px;
    color: var(--text-muted, #5e7a90);
    font-size: 12px;
}

.booking-appointment-edit .ba-dl {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0;
}

@media (max-width: 991px) {
    .booking-appointment-edit .ba-dl {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 575px) {
    .booking-appointment-edit .ba-dl {
        grid-template-columns: 1fr;
    }
}

.booking-appointment-edit .ba-dl__item {
    padding: 0.75rem 0.5rem;
    border-bottom: 1px solid rgba(200, 220, 239, 0.7);
}

.booking-appointment-edit .ba-label {
    display: block;
    margin-bottom: 3px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-muted, #5e7a90);
}

.booking-appointment-edit .ba-value {
    color: var(--text-dark, #1a2c40);
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.4;
    word-break: break-word;
}

.booking-appointment-edit .ba-value__sub {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-muted, #5e7a90);
}

.booking-appointment-edit .ba-code {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(30, 61, 96, 0.06);
    color: var(--navy, #1e3d60);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    font-weight: 600;
}

.booking-appointment-edit .ba-badge {
    display: inline-flex;
    align-items: center;
    font-weight: 600;
    font-size: 11px;
    border-radius: 999px;
    padding: 0.3em 0.65em;
}

.booking-appointment-edit .ba-badge--success { background: rgba(30, 122, 82, 0.12); color: var(--success, #1e7a52); }
.booking-appointment-edit .ba-badge--warning { background: rgba(200, 153, 42, 0.15); color: #7a5800; }
.booking-appointment-edit .ba-badge--danger { background: rgba(168, 48, 32, 0.12); color: var(--danger, #a83020); }
.booking-appointment-edit .ba-badge--secondary { background: rgba(94, 122, 144, 0.12); color: var(--text-muted, #5e7a90); }

.booking-appointment-edit .ba-actions {
    margin-top: 4px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    padding: 4px 0 0.25rem;
}

.booking-appointment-edit .ba-actions .btn {
    margin: 0;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.55rem 1.05rem;
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.booking-appointment-edit .ba-actions .btn-primary {
    background: var(--navy, #1e3d60) !important;
    border: 1px solid var(--navy, #1e3d60) !important;
    color: #fff !important;
}

.booking-appointment-edit .ba-actions .btn-primary:hover {
    background: var(--sidebar-active, #3a6fa8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
}

.booking-appointment-edit .ba-actions .btn-outline-secondary {
    background: var(--card-bg, #fff) !important;
    border: 1px solid var(--border, #c8dcef) !important;
    color: var(--navy, #1e3d60) !important;
}

.booking-appointment-edit .ba-actions .btn-outline-secondary:hover {
    background: var(--sidebar-bg, #ddeaf8) !important;
}
</style>

<div class="section-body booking-appointment-edit">
    <div class="ba-toolbar">
        <a href="{{ route('booking.appointments.show', $appointment->id) }}" class="btn btn-sm btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Appointment Details
        </a>
    </div>

    <div class="ba-shell">
        <div class="ba-shell__header">
            <h4 class="ba-shell__title">
                <i class="fa-solid fa-pen-to-square"></i>
                Edit Appointment
                <small>#{{ $appointment->id }}</small>
            </h4>
        </div>

        <div class="ba-shell__body">
            <p class="ba-hint">
                <i class="fa-solid fa-circle-info"></i>
                Update the appointment date, time, meeting type, and preferred language.
            </p>

            @if(session('success'))
            <div class="alert ba-alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            @endif

            @if(session('error'))
            <div class="alert ba-alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            @endif

            @if($errors->any())
            <div class="alert ba-alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            @endif

            <form method="POST" action="{{ route('booking.appointments.update', $appointment->id) }}" class="needs-validation" novalidate>
                @csrf
                @method('PUT')

                <section class="ba-panel">
                    <div class="ba-panel__head">
                        <h5 class="ba-panel__title">
                            <span class="ba-panel__icon"><i class="fa-solid fa-calendar-days"></i></span>
                            Schedule
                        </h5>
                    </div>
                    <div class="ba-panel__body">
                        <div class="ba-form-grid">
                            <div class="ba-field">
                                <label for="appointment-date">Appointment Date</label>
                                <input type="date"
                                       class="form-control"
                                       id="appointment-date"
                                       name="appointment_date"
                                       value="{{ old('appointment_date', $appointment->appointment_datetime->format('Y-m-d')) }}"
                                       required
                                       onchange="validateWeekendDate(this)"
                                       data-original-date="{{ $appointment->appointment_datetime->format('Y-m-d') }}">
                                <div class="invalid-feedback">Please select a valid appointment date.</div>
                            </div>
                            <div class="ba-field">
                                <label for="appointment-time">Appointment Time</label>
                                <input type="time"
                                       class="form-control"
                                       id="appointment-time"
                                       name="appointment_time"
                                       value="{{ old('appointment_time', $appointment->appointment_datetime->format('H:i')) }}"
                                       required>
                                <div class="invalid-feedback">Please select a valid appointment time.</div>
                            </div>
                            <div class="ba-field">
                                <label for="meeting-type">Meeting Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="meeting-type" name="meeting_type" required>
                                    <option value="in_person" {{ old('meeting_type', $appointment->meeting_type) == 'in_person' ? 'selected' : '' }}>In Person</option>
                                    <option value="phone" {{ old('meeting_type', $appointment->meeting_type) == 'phone' ? 'selected' : '' }}>Phone</option>
                                    @if($appointment->is_paid)
                                    <option value="video" {{ old('meeting_type', $appointment->meeting_type) == 'video' ? 'selected' : '' }}>Video</option>
                                    @endif
                                </select>
                                <div class="invalid-feedback">Please select a meeting type.</div>
                                @if(!$appointment->is_paid)
                                <small class="form-text">Video meeting type is only available for paid appointments.</small>
                                @endif
                            </div>
                            <div class="ba-field">
                                <label for="preferred-language">Preferred Language <span class="text-danger">*</span></label>
                                <select class="form-control" id="preferred-language" name="preferred_language" required>
                                    <option value="English" {{ old('preferred_language', $appointment->preferred_language ?? 'English') == 'English' ? 'selected' : '' }}>English</option>
                                    <option value="Hindi" {{ old('preferred_language', $appointment->preferred_language ?? 'English') == 'Hindi' ? 'selected' : '' }}>Hindi</option>
                                    <option value="Punjabi" {{ old('preferred_language', $appointment->preferred_language ?? 'English') == 'Punjabi' ? 'selected' : '' }}>Punjabi</option>
                                </select>
                                <div class="invalid-feedback">Please select a preferred language.</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ba-panel">
                    <div class="ba-panel__head">
                        <h5 class="ba-panel__title">
                            <span class="ba-panel__icon ba-panel__icon--navy"><i class="fa-solid fa-circle-info"></i></span>
                            Appointment Summary
                        </h5>
                    </div>
                    <div class="ba-panel__body">
                        <div class="ba-dl">
                            <div class="ba-dl__item">
                                <span class="ba-label">Client</span>
                                <div class="ba-value">
                                    {{ $appointment->client_name }}
                                    <span class="ba-value__sub">{{ $appointment->client_email }}</span>
                                    <span class="ba-value__sub">{{ $appointment->client_phone }}</span>
                                </div>
                            </div>
                            <div class="ba-dl__item">
                                <span class="ba-label">Current Schedule</span>
                                <div class="ba-value">
                                    {{ $appointment->appointment_datetime->format('l, d M Y') }}
                                    <span class="ba-value__sub">{{ $appointment->appointment_datetime->format('h:i A') }}</span>
                                </div>
                            </div>
                            <div class="ba-dl__item">
                                <span class="ba-label">Time Zone</span>
                                <div class="ba-value">{{ $appointment->client_timezone ?? config('app.timezone') }}</div>
                            </div>
                            <div class="ba-dl__item">
                                <span class="ba-label">Location</span>
                                <div class="ba-value">{{ $locationLabel }}</div>
                            </div>
                            <div class="ba-dl__item">
                                <span class="ba-label">Nature of Enquiry</span>
                                <div class="ba-value">{{ $noeDisplay }}</div>
                            </div>
                            <div class="ba-dl__item">
                                <span class="ba-label">Service Type</span>
                                <div class="ba-value">{{ $serviceTypeDisplay }}</div>
                            </div>
                            <div class="ba-dl__item">
                                <span class="ba-label">Website Booking ID</span>
                                <div class="ba-value">
                                    @if($appointment->bansal_appointment_id)
                                        <span class="ba-code">{{ $appointment->bansal_appointment_id }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                            <div class="ba-dl__item">
                                <span class="ba-label">Sync Status</span>
                                <div class="ba-value">
                                    <span class="ba-badge ba-badge--{{ $syncStatusClass }}">{{ $syncStatusText }}</span>
                                    @if($appointment->sync_error)
                                        <span class="ba-value__sub" style="color: var(--danger, #a83020);">{{ Str::limit($appointment->sync_error, 50) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="ba-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Update Appointment
                    </button>
                    <a href="{{ route('booking.appointments.show', $appointment->id) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

window.validateWeekendDate = function(dateInput) {
    if (!dateInput.value) {
        return;
    }

    const selectedDate = new Date(dateInput.value);
    const dayOfWeek = selectedDate.getDay();

    if (dayOfWeek === 0 || dayOfWeek === 6) {
        const originalDate = dateInput.getAttribute('data-original-date') || '{{ $appointment->appointment_datetime->format("Y-m-d") }}';
        dateInput.value = originalDate;
        crmAlert('Weekends (Saturday and Sunday) are not available for appointments. Please select a weekday.');
        return false;
    }

    return true;
};
</script>
@endsection
