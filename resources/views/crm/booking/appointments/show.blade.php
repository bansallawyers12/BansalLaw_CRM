@extends('layouts.crm_client_detail')
@section('title', 'Appointment Details - #' . $appointment->id)

@section('content')

@php
    $statusClass = match($appointment->status) {
        'pending' => 'warning',
        'confirmed' => 'success',
        'completed' => 'info',
        'cancelled' => 'danger',
        'no_show' => 'dark',
        'paid' => 'primary',
        default => 'secondary'
    };
    $statusLabel = ucfirst(str_replace('_', ' ', (string) $appointment->status));

    $humanize = static function ($value, $fallback = '—') {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return $fallback;
        }
        return \Illuminate\Support\Str::of($raw)->replace(['_', '-'], ' ')->title()->toString();
    };

    $locationLabel = $humanize($appointment->location);
    $meetingTypeLabel = $humanize($appointment->meeting_type);
    $serviceTypeLabel = $humanize($appointment->service_type);
    $enquiryTypeLabel = $humanize($appointment->enquiry_type);
    $consultantName = $appointment->consultant?->name;

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
/* Appointment detail — denser Powder Blue layout (docs/theme.md), page-scoped */
.booking-appointment-show {
    overflow-x: hidden;
    max-width: 100%;
    padding-bottom: 2rem;
}

.booking-appointment-show .ba-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    padding: 12px 4px 16px;
    margin: 0 0 4px;
}

.booking-appointment-show .ba-toolbar .btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.5rem 1rem;
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    line-height: 1.2;
}

.booking-appointment-show .ba-toolbar .btn-secondary {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border, #c8dcef);
    color: var(--navy, #1e3d60);
}

.booking-appointment-show .ba-toolbar .btn-secondary:hover {
    background: var(--sidebar-bg, #ddeaf8);
    color: var(--navy, #1e3d60);
}

.booking-appointment-show .ba-shell__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.booking-appointment-show .ba-shell__actions .btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.45rem 0.95rem;
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    line-height: 1.2;
    margin: 0;
}

.booking-appointment-show .ba-shell__actions .btn-primary {
    background: var(--navy, #1e3d60) !important;
    border: 1px solid var(--navy, #1e3d60) !important;
    color: #fff !important;
}

.booking-appointment-show .ba-shell__actions .btn-primary:hover {
    background: var(--sidebar-active, #3a6fa8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
}

.booking-appointment-show .ba-shell__actions .btn-info {
    background: var(--sidebar-active, #3a6fa8) !important;
    border: 1px solid var(--sidebar-active, #3a6fa8) !important;
    color: #fff !important;
}

.booking-appointment-show .ba-shell__actions .btn-info:hover {
    background: var(--navy, #1e3d60) !important;
    border-color: var(--navy, #1e3d60) !important;
}

.booking-appointment-show > .ba-shell {
    border: 1px solid var(--border, #c8dcef);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(30, 61, 96, 0.06);
    background: var(--card-bg, #fff);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.booking-appointment-show .ba-shell__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 16px;
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--border, #c8dcef);
}

.booking-appointment-show .ba-shell__title {
    margin: 0;
    color: var(--navy, #1e3d60);
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.booking-appointment-show .ba-shell__title small {
    color: var(--text-muted, #5e7a90);
    font-weight: 500;
    font-size: 0.85rem;
}

.booking-appointment-show .ba-status {
    display: inline-flex;
    align-items: center;
    font-weight: 600;
    font-size: 12px;
    border-radius: 999px;
    padding: 0.4em 0.85em;
}

.booking-appointment-show .ba-status--warning { background: rgba(200, 153, 42, 0.15); color: #7a5800; }
.booking-appointment-show .ba-status--success { background: rgba(30, 122, 82, 0.12); color: var(--success, #1e7a52); }
.booking-appointment-show .ba-status--info { background: rgba(58, 111, 168, 0.12); color: var(--sidebar-active, #3a6fa8); }
.booking-appointment-show .ba-status--danger { background: rgba(168, 48, 32, 0.12); color: var(--danger, #a83020); }
.booking-appointment-show .ba-status--dark,
.booking-appointment-show .ba-status--secondary { background: rgba(94, 122, 144, 0.12); color: var(--text-muted, #5e7a90); }
.booking-appointment-show .ba-status--primary { background: rgba(30, 61, 96, 0.12); color: var(--navy, #1e3d60); }

.booking-appointment-show .ba-shell__body {
    padding: 1.1rem 1.2rem 1.75rem;
    background: var(--page-bg, #f0f6ff);
}

.booking-appointment-show .ba-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

@media (max-width: 991px) {
    .booking-appointment-show .ba-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575px) {
    .booking-appointment-show .ba-summary { grid-template-columns: 1fr; }
}

.booking-appointment-show .ba-summary__item {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border, #c8dcef);
    border-radius: 10px;
    padding: 12px 14px;
    min-height: 72px;
}

.booking-appointment-show .ba-summary__label {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-muted, #5e7a90);
}

.booking-appointment-show .ba-summary__value {
    color: var(--navy, #1e3d60);
    font-weight: 700;
    font-size: 0.95rem;
    line-height: 1.3;
}

.booking-appointment-show .ba-summary__sub {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-muted, #5e7a90);
}

.booking-appointment-show .ba-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 14px;
    align-items: start;
}

@media (max-width: 991px) {
    .booking-appointment-show .ba-grid { grid-template-columns: 1fr; }
}

.booking-appointment-show .ba-col {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.booking-appointment-show .ba-panel {
    background: var(--card-bg, #fff);
    border: 1px solid var(--border, #c8dcef);
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(30, 61, 96, 0.05);
    overflow: hidden;
    height: fit-content;
}

.booking-appointment-show .ba-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border, #c8dcef);
    background: linear-gradient(180deg, #f8fbff 0%, #fff 100%);
}

.booking-appointment-show .ba-panel__title {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: var(--navy, #1e3d60);
    font-size: 0.92rem;
    font-weight: 700;
}

.booking-appointment-show .ba-panel__icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}

.booking-appointment-show .ba-panel__icon--navy { background: rgba(30, 61, 96, 0.1); color: var(--navy, #1e3d60); }
.booking-appointment-show .ba-panel__icon--blue { background: rgba(58, 111, 168, 0.14); color: var(--sidebar-active, #3a6fa8); }
.booking-appointment-show .ba-panel__icon--gold { background: rgba(200, 153, 42, 0.15); color: var(--accent-gold, #c8992a); }
.booking-appointment-show .ba-panel__icon--success { background: rgba(30, 122, 82, 0.12); color: var(--success, #1e7a52); }
.booking-appointment-show .ba-panel__icon--muted { background: rgba(94, 122, 144, 0.12); color: var(--text-muted, #5e7a90); }

.booking-appointment-show .ba-panel__body {
    padding: 0.25rem 1rem 0.9rem;
}

.booking-appointment-show .ba-dl {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}

@media (max-width: 575px) {
    .booking-appointment-show .ba-dl { grid-template-columns: 1fr; }
}

.booking-appointment-show .ba-dl__item {
    padding: 0.7rem 0.35rem;
    border-bottom: 1px solid rgba(200, 220, 239, 0.7);
}

.booking-appointment-show .ba-dl__item--full {
    grid-column: 1 / -1;
}

.booking-appointment-show .ba-dl__item:nth-last-child(1),
.booking-appointment-show .ba-dl__item:nth-last-child(2):nth-child(odd) {
    border-bottom: none;
}

.booking-appointment-show .ba-label {
    display: block;
    margin-bottom: 3px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--text-muted, #5e7a90);
}

.booking-appointment-show .ba-value {
    color: var(--text-dark, #1a2c40);
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.4;
    word-break: break-word;
}

.booking-appointment-show .ba-value a {
    color: var(--sidebar-active, #3a6fa8);
    text-decoration: none;
    font-weight: 600;
}

.booking-appointment-show .ba-value a:hover { color: var(--navy, #1e3d60); }

.booking-appointment-show .ba-value__sub {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-muted, #5e7a90);
}

.booking-appointment-show .ba-code {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(30, 61, 96, 0.06);
    color: var(--navy, #1e3d60);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    font-weight: 600;
}

.booking-appointment-show .ba-enquiry {
    margin-top: 4px;
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--page-bg, #f0f6ff);
    border: 1px solid var(--border, #c8dcef);
    color: var(--text-dark, #1a2c40);
    font-weight: 500;
    white-space: pre-wrap;
}

.booking-appointment-show .ba-client-cta {
    margin-top: 0.35rem;
    padding-top: 0.35rem;
}

.booking-appointment-show .ba-client-cta .btn {
    border-radius: 8px;
    font-weight: 600;
    background: var(--navy, #1e3d60) !important;
    border-color: var(--navy, #1e3d60) !important;
    color: #fff !important;
}

.booking-appointment-show .ba-panel__add {
    border-radius: 8px !important;
    font-weight: 600;
    font-size: 12px;
    padding: 0.25rem 0.65rem;
    background: #fff !important;
    border: 1px solid var(--border, #c8dcef) !important;
    color: var(--navy, #1e3d60) !important;
}

.booking-appointment-show .ba-empty {
    margin: 0.45rem 0 0;
    color: var(--text-muted, #5e7a90);
    font-size: 0.88rem;
}

.booking-appointment-show .ba-note {
    background: var(--page-bg, #f0f6ff);
    border: 1px solid var(--border, #c8dcef);
    border-left: 3px solid var(--accent-gold, #c8992a);
    border-radius: 0 8px 8px 0;
    padding: 10px 12px;
    margin-top: 8px;
}

.booking-appointment-show .ba-note__meta {
    font-size: 0.78rem;
    color: var(--text-muted, #5e7a90);
    margin-bottom: 4px;
}

.booking-appointment-show .ba-note__meta strong { color: var(--navy, #1e3d60); }

.booking-appointment-show .ba-note__content {
    color: var(--text-dark, #1a2c40);
    font-size: 0.88rem;
}

.booking-appointment-show .ba-notify {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-top: 8px;
}

.booking-appointment-show .ba-notify__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--page-bg, #f0f6ff);
    border: 1px solid var(--border, #c8dcef);
}

.booking-appointment-show .ba-notify__label {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-dark, #1a2c40);
}

.booking-appointment-show .ba-notify__meta {
    display: block;
    font-size: 11px;
    font-weight: 500;
    color: var(--text-muted, #5e7a90);
}

.booking-appointment-show .ba-badge {
    display: inline-flex;
    align-items: center;
    font-weight: 600;
    font-size: 11px;
    border-radius: 999px;
    padding: 0.3em 0.65em;
    white-space: nowrap;
}

.booking-appointment-show .ba-badge--success { background: rgba(30, 122, 82, 0.12); color: var(--success, #1e7a52); }
.booking-appointment-show .ba-badge--warning { background: rgba(200, 153, 42, 0.15); color: #7a5800; }
.booking-appointment-show .ba-badge--danger { background: rgba(168, 48, 32, 0.12); color: var(--danger, #a83020); }
.booking-appointment-show .ba-badge--secondary { background: rgba(94, 122, 144, 0.12); color: var(--text-muted, #5e7a90); }
.booking-appointment-show .ba-badge--info { background: rgba(58, 111, 168, 0.12); color: var(--sidebar-active, #3a6fa8); }

.booking-appointment-show .ba-actions {
    margin-top: 16px;
    margin-bottom: 0.5rem;
    background: transparent;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    overflow: visible;
}

.booking-appointment-show .ba-actions__body {
    padding: 0.25rem 0 0.5rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.booking-appointment-show .ba-actions__body .btn {
    margin: 0;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.55rem 1.05rem;
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    line-height: 1.2;
    box-shadow: none;
}

.booking-appointment-show .ba-actions__body .btn i {
    font-size: 0.9rem;
}

.booking-appointment-show .ba-actions__body .btn-success {
    background: var(--success, #1e7a52) !important;
    border: 1px solid var(--success, #1e7a52) !important;
    color: #fff !important;
}

.booking-appointment-show .ba-actions__body .btn-success:hover {
    filter: brightness(0.95);
}

.booking-appointment-show .ba-actions__body .btn-primary {
    background: var(--navy, #1e3d60) !important;
    border: 1px solid var(--navy, #1e3d60) !important;
    color: #fff !important;
}

.booking-appointment-show .ba-actions__body .btn-primary:hover {
    background: var(--sidebar-active, #3a6fa8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
}

.booking-appointment-show .ba-actions__body .btn-danger {
    background: var(--danger, #a83020) !important;
    border: 1px solid var(--danger, #a83020) !important;
    color: #fff !important;
}

.booking-appointment-show .ba-actions__body .btn-danger:hover {
    filter: brightness(0.95);
}

.booking-appointment-show .ba-actions__body .btn-info {
    background: var(--sidebar-active, #3a6fa8) !important;
    border: 1px solid var(--sidebar-active, #3a6fa8) !important;
    color: #fff !important;
}

.booking-appointment-show .ba-actions__body .btn-info:hover {
    background: var(--navy, #1e3d60) !important;
    border-color: var(--navy, #1e3d60) !important;
}

.booking-appointment-show .ba-actions__body .btn-outline-secondary {
    background: var(--card-bg, #fff) !important;
    border: 1px solid var(--border, #c8dcef) !important;
    color: var(--navy, #1e3d60) !important;
}

.booking-appointment-show .ba-actions__body .btn-outline-secondary:hover {
    background: var(--sidebar-bg, #ddeaf8) !important;
    border-color: var(--border, #c8dcef) !important;
    color: var(--navy, #1e3d60) !important;
}

@media (max-width: 575px) {
    .booking-appointment-show .ba-actions__body .btn {
        flex: 1 1 calc(50% - 10px);
        justify-content: center;
    }
}

@media print {
    .booking-appointment-show .ba-toolbar,
    .booking-appointment-show .ba-actions { display: none !important; }
    .booking-appointment-show .ba-shell__body { background: #fff; }
}
</style>

<div class="section-body booking-appointment-show">
    <div class="ba-toolbar">
        <a href="{{ route('booking.appointments.index') }}" class="btn btn-sm btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="ba-shell">
        <div class="ba-shell__header">
            <h4 class="ba-shell__title">
                <i class="fa-solid fa-calendar-check"></i>
                Appointment Details
                <small>#{{ $appointment->id }}</small>
            </h4>
            <div class="ba-shell__actions">
                <span class="ba-status ba-status--{{ $statusClass }}">{{ $statusLabel }}</span>
                <a href="{{ route('booking.appointments.edit', $appointment->id) }}" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-pen-to-square"></i> Edit
                </a>
                @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                <a href="{{ route('booking.sync.dashboard') }}" class="btn btn-sm btn-info">
                    <i class="fa-solid fa-rotate"></i> Sync Status
                </a>
                @endif
            </div>
        </div>

        <div class="ba-shell__body">
            <div class="ba-summary">
                <div class="ba-summary__item">
                    <span class="ba-summary__label">When</span>
                    <div class="ba-summary__value">{{ $appointment->appointment_datetime->format('d M Y') }}</div>
                    <span class="ba-summary__sub">{{ $appointment->appointment_datetime->format('l · h:i A') }} · {{ $appointment->duration_minutes }} min</span>
                </div>
                <div class="ba-summary__item">
                    <span class="ba-summary__label">Where</span>
                    <div class="ba-summary__value">{{ $locationLabel }}</div>
                    <span class="ba-summary__sub">{{ $meetingTypeLabel }}@if($appointment->location === 'inperson' && $appointment->inperson_address) · {{ $appointment->inperson_address }}@endif</span>
                </div>
                <div class="ba-summary__item">
                    <span class="ba-summary__label">Service</span>
                    <div class="ba-summary__value">{{ $serviceTypeLabel }}</div>
                    <span class="ba-summary__sub">{{ $enquiryTypeLabel }}</span>
                </div>
                <div class="ba-summary__item">
                    <span class="ba-summary__label">Consultant</span>
                    <div class="ba-summary__value">{{ $consultantName ?: 'Not Assigned' }}</div>
                    <span class="ba-summary__sub">{{ $appointment->preferred_language ?? 'English' }}</span>
                </div>
            </div>

            <div class="ba-grid">
                <div class="ba-col">
                    <section class="ba-panel">
                        <div class="ba-panel__head">
                            <h5 class="ba-panel__title">
                                <span class="ba-panel__icon ba-panel__icon--navy"><i class="fa-solid fa-user"></i></span>
                                Client
                            </h5>
                        </div>
                        <div class="ba-panel__body">
                            <div class="ba-dl">
                                <div class="ba-dl__item">
                                    <span class="ba-label">Name</span>
                                    <div class="ba-value">{{ $appointment->client_name }}</div>
                                </div>
                                <div class="ba-dl__item">
                                    <span class="ba-label">Timezone</span>
                                    <div class="ba-value">{{ $appointment->client_timezone ?? '—' }}</div>
                                </div>
                                <div class="ba-dl__item">
                                    <span class="ba-label">Email</span>
                                    <div class="ba-value"><a href="mailto:{{ $appointment->client_email }}">{{ $appointment->client_email }}</a></div>
                                </div>
                                <div class="ba-dl__item">
                                    <span class="ba-label">Phone</span>
                                    <div class="ba-value"><a href="tel:{{ $appointment->client_phone }}">{{ $appointment->client_phone }}</a></div>
                                </div>
                                @if($appointment->enquiry_details)
                                <div class="ba-dl__item ba-dl__item--full">
                                    <span class="ba-label">Enquiry Details</span>
                                    <div class="ba-enquiry">{{ $appointment->enquiry_details }}</div>
                                </div>
                                @endif
                            </div>
                            @if($appointment->client_id)
                            @php
                                $clientDetailParams = [base64_encode(convert_uuencode($appointment->client_id))];
                                $latestMatterRef = optional($latestClientMatter)->client_unique_matter_no;
                                if (!empty($latestMatterRef)) {
                                    $clientDetailParams[] = $latestMatterRef;
                                }
                            @endphp
                            <div class="ba-client-cta">
                                <a href="{{ route('clients.detail', $clientDetailParams) }}" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fa-solid fa-up-right-from-square"></i> View Client Profile
                                </a>
                            </div>
                            @endif
                        </div>
                    </section>

                    @if($appointment->is_paid)
                    <section class="ba-panel">
                        <div class="ba-panel__head">
                            <h5 class="ba-panel__title">
                                <span class="ba-panel__icon ba-panel__icon--success"><i class="fa-solid fa-dollar-sign"></i></span>
                                Payment
                            </h5>
                        </div>
                        <div class="ba-panel__body">
                            <div class="ba-dl">
                                <div class="ba-dl__item">
                                    <span class="ba-label">Status</span>
                                    <div class="ba-value"><span class="ba-badge ba-badge--success">Paid</span></div>
                                </div>
                                <div class="ba-dl__item">
                                    <span class="ba-label">Final Amount</span>
                                    <div class="ba-value">${{ number_format($appointment->final_amount, 2) }}</div>
                                </div>
                                <div class="ba-dl__item">
                                    <span class="ba-label">Amount</span>
                                    <div class="ba-value">${{ number_format($appointment->amount, 2) }}</div>
                                </div>
                                @if($appointment->discount_amount > 0)
                                <div class="ba-dl__item">
                                    <span class="ba-label">Discount</span>
                                    <div class="ba-value" style="color: var(--success, #1e7a52);">-${{ number_format($appointment->discount_amount, 2) }}</div>
                                </div>
                                @endif
                                <div class="ba-dl__item">
                                    <span class="ba-label">Method</span>
                                    <div class="ba-value">{{ $humanize($appointment->payment_method) }}</div>
                                </div>
                                <div class="ba-dl__item">
                                    <span class="ba-label">Paid At</span>
                                    <div class="ba-value">{{ $appointment->paid_at ? $appointment->paid_at->format('d M Y, h:i A') : '—' }}</div>
                                </div>
                                @if($appointment->promo_code)
                                <div class="ba-dl__item ba-dl__item--full">
                                    <span class="ba-label">Promo Code</span>
                                    <div class="ba-value"><span class="ba-badge ba-badge--info">{{ $appointment->promo_code }}</span></div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </section>
                    @endif
                </div>

                <div class="ba-col">
                    <section class="ba-panel">
                        <div class="ba-panel__head">
                            <h5 class="ba-panel__title">
                                <span class="ba-panel__icon ba-panel__icon--gold"><i class="fa-solid fa-note-sticky"></i></span>
                                Admin Notes
                            </h5>
                            <button type="button" class="btn btn-sm ba-panel__add" onclick="addNote()">
                                <i class="fa-solid fa-plus"></i> Add
                            </button>
                        </div>
                        <div class="ba-panel__body">
                            @if($appointment->admin_notes)
                                @php
                                    $notes = is_string($appointment->admin_notes) ? json_decode($appointment->admin_notes, true) : $appointment->admin_notes;
                                @endphp
                                @if(is_array($notes) && count($notes) > 0)
                                    @foreach($notes as $note)
                                    <div class="ba-note">
                                        <div class="ba-note__meta">
                                            <strong>{{ $note['author'] ?? 'Admin' }}</strong>
                                            · {{ $note['created_at'] ?? now()->format('d M Y, h:i A') }}
                                        </div>
                                        <div class="ba-note__content">{{ $note['content'] ?? $note }}</div>
                                    </div>
                                    @endforeach
                                @else
                                    <p class="ba-empty">{{ $appointment->admin_notes }}</p>
                                @endif
                            @else
                                <p class="ba-empty">No admin notes yet.</p>
                            @endif
                        </div>
                    </section>

                    <section class="ba-panel">
                        <div class="ba-panel__head">
                            <h5 class="ba-panel__title">
                                <span class="ba-panel__icon ba-panel__icon--blue"><i class="fa-solid fa-rotate"></i></span>
                                Sync &amp; Notifications
                            </h5>
                        </div>
                        <div class="ba-panel__body">
                            <div class="ba-dl">
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
                                    <div class="ba-value"><span class="ba-badge ba-badge--{{ $syncStatusClass }}">{{ $syncStatusText }}</span></div>
                                </div>
                                <div class="ba-dl__item">
                                    <span class="ba-label">First Synced</span>
                                    <div class="ba-value">{{ $appointment->synced_from_bansal_at ? $appointment->synced_from_bansal_at->format('d M Y, h:i A') : '—' }}</div>
                                </div>
                                <div class="ba-dl__item">
                                    <span class="ba-label">Last Updated</span>
                                    <div class="ba-value">{{ $appointment->last_synced_at ? $appointment->last_synced_at->format('d M Y, h:i A') : '—' }}</div>
                                </div>
                                @if($appointment->order_hash)
                                <div class="ba-dl__item ba-dl__item--full">
                                    <span class="ba-label">Order Hash</span>
                                    <div class="ba-value"><span class="ba-code">{{ $appointment->order_hash }}</span></div>
                                </div>
                                @endif
                            </div>

                            <div class="ba-notify">
                                <div class="ba-notify__row">
                                    <div>
                                        <span class="ba-notify__label">Confirmation Email</span>
                                        @if($appointment->confirmation_email_sent && $appointment->confirmation_email_sent_at)
                                            <span class="ba-notify__meta">{{ $appointment->confirmation_email_sent_at->format('d M Y') }}</span>
                                        @endif
                                    </div>
                                    @if($appointment->confirmation_email_sent)
                                        <span class="ba-badge ba-badge--success">Sent</span>
                                    @else
                                        <span class="ba-badge ba-badge--secondary">Not Sent</span>
                                    @endif
                                </div>
                                <div class="ba-notify__row">
                                    <div>
                                        <span class="ba-notify__label">Reminder SMS</span>
                                        @if($appointment->reminder_sms_sent && $appointment->reminder_sms_sent_at)
                                            <span class="ba-notify__meta">{{ $appointment->reminder_sms_sent_at->format('d M Y') }}</span>
                                        @endif
                                    </div>
                                    @if($appointment->reminder_sms_sent)
                                        <span class="ba-badge ba-badge--success">Sent</span>
                                    @else
                                        <span class="ba-badge ba-badge--secondary">Not Sent</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="ba-actions">
                <div class="ba-actions__body">
                    @if($appointment->status === 'pending')
                    <button type="button" class="btn btn-success" onclick="updateStatus('confirmed')">
                        <i class="fa-solid fa-check"></i> Confirm
                    </button>
                    @endif

                    @if(in_array($appointment->status, ['pending', 'confirmed']))
                    <button type="button" class="btn btn-primary" onclick="markCompleteAppointment()">
                        <i class="fa-solid fa-circle-check"></i> Complete
                    </button>
                    <button type="button" class="btn btn-danger" onclick="cancelAppointment()">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </button>
                    @endif

                    <button type="button" class="btn btn-info" onclick="sendSMS()">
                        <i class="fa-solid fa-comment-sms"></i> Send SMS
                    </button>

                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStatus(newStatus) {
    if (!confirm('Update appointment status to ' + newStatus + '?')) {
        return;
    }

    $.ajax({
        url: '{{ route("booking.appointments.update-status", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            status: newStatus
        },
        success: function(response) {
            if (response.success) {
                crmAlert('Status updated successfully!');
                window.location.reload();
            }
        },
        error: function() {
            crmAlert('Failed to update status');
        }
    });
}

function cancelAppointment() {
    const reason = prompt('Please enter cancellation reason (required):');
    if (!reason || reason.trim() === '') {
        crmAlert('Cancellation reason is required. Operation cancelled.');
        return;
    }

    $.ajax({
        url: '{{ route("booking.appointments.update-status", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            status: 'cancelled',
            cancellation_reason: reason.trim()
        },
        success: function(response) {
            if (response.success) {
                crmAlert(response.message || 'Appointment cancelled successfully!');
                window.location.reload();
            } else {
                crmAlert(response.message || 'Failed to cancel appointment');
            }
        },
        error: function() {
            crmAlert('Failed to cancel appointment');
        }
    });
}

function markCompleteAppointment() {
    if (!confirm('Mark appointment as completed?')) {
        return;
    }

    $.ajax({
        url: '{{ route("booking.appointments.update-status", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            status: 'completed'
        },
        success: function(response) {
            if (response.success) {
                crmAlert('Appointment completed successfully!');
                window.location.reload();
            }
        },
        error: function() {
            crmAlert('Failed to complete appointment');
        }
    });
}

function sendReminder() {
    if (!confirm('Send appointment reminder to {{ $appointment->client_email }}?')) {
        return;
    }

    $.ajax({
        url: '{{ route("booking.appointments.send-reminder", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                crmAlert('Reminder sent successfully!');
            }
        },
        error: function() {
            crmAlert('Failed to send reminder');
        }
    });
}

function sendSMS() {
    if (!confirm('Send SMS reminder to {{ $appointment->client_phone }}?')) {
        return;
    }

    crmAlert('SMS functionality will be implemented');
}

function addNote() {
    const note = prompt('Enter admin note:');
    if (!note) return;

    $.ajax({
        url: '{{ route("booking.appointments.add-note", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            note: note
        },
        success: function(response) {
            if (response.success) {
                crmAlert('Note added successfully!');
                window.location.reload();
            }
        },
        error: function() {
            crmAlert('Failed to add note');
        }
    });
}
</script>

@endsection
