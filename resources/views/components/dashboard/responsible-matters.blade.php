@props([
    'matters' => null,
])

@php
    $matters = $matters ?? collect();
@endphp

@if($matters->isNotEmpty())
<section class="focus-container responsible-matters" id="responsible-matters" aria-label="Matters you are person responsible for">
    <div class="focus-header">
        <div class="focus-header-left">
            <div class="dashboard-section-icon-badge dashboard-section-icon-badge--responsible">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div>
                <h3>Matters I'm responsible for</h3>
                <span class="dashboard-section-sub">Open matters where you are person responsible</span>
            </div>
        </div>
        <span class="badge-count">{{ $matters->count() }}</span>
    </div>
    <div class="case-list-container">
        <ul class="case-list">
            @foreach($matters as $matter)
                @php
                    $client = $matter->client;
                    $detailUrl = ($client && ! empty($matter->client_unique_matter_no))
                        ? route('clients.detail', [base64_encode(convert_uuencode($client->id)), $matter->client_unique_matter_no])
                        : null;
                @endphp
                <li class="case-list-item">
                    <div class="case-details">
                        <span class="client-name">
                            @if($detailUrl)
                                <a href="{{ $detailUrl }}">{{ $matter->dashboard_display_name }}</a>
                            @else
                                {{ $matter->dashboard_display_name }}
                            @endif
                        </span>
                        <span class="case-info">
                            @if($detailUrl)
                                <a href="{{ $detailUrl }}">{{ $matter->dashboard_matter_label }}</a>
                            @else
                                {{ $matter->dashboard_matter_label }}
                            @endif
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
