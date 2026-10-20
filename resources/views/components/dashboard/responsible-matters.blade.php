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
            <h3>Matters I'm responsible for</h3>
        </div>
        <span class="badge-count">{{ $matters->count() }}</span>
    </div>
    <div class="responsible-matters-scroll">
        <table class="responsible-matters-table">
            <thead>
                <tr>
                    <th>Matter</th>
                    <th>Client</th>
                    <th>DOB</th>
                    <th>Last activity</th>
                    <th>Created</th>
                    <th>Stage</th>
                    <th>Legal practitioner</th>
                    <th>Person assisting</th>
                    <th class="responsible-matters-table__actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matters as $matter)
                    @php
                        $client = $matter->client;
                        $detailUrl = ($client && ! empty($matter->client_unique_matter_no))
                            ? route('clients.detail', [base64_encode(convert_uuencode($client->id)), $matter->client_unique_matter_no])
                            : null;
                        $activityText = trim((string) ($matter->last_activity_text ?? ''));
                        if ($activityText === '') {
                            $activityText = '—';
                        }
                    @endphp
                    <tr>
                        <td>
                            @if($detailUrl)
                                <a class="responsible-matters-primary" href="{{ $detailUrl }}" title="Open matter">{{ $matter->dashboard_matter_title }}</a>
                            @else
                                <span class="responsible-matters-primary">{{ $matter->dashboard_matter_title }}</span>
                            @endif
                            <span class="responsible-matters-chip">{{ $matter->dashboard_matter_ref }}</span>
                        </td>
                        <td>
                            @if($detailUrl)
                                <a class="responsible-matters-primary" href="{{ $detailUrl }}" title="Open matter">{{ $matter->dashboard_display_name }}</a>
                            @else
                                <span class="responsible-matters-primary">{{ $matter->dashboard_display_name }}</span>
                            @endif
                            <span class="responsible-matters-meta">{{ $matter->dashboard_client_ref }}</span>
                        </td>
                        <td><span class="responsible-matters-date">{{ $matter->dashboard_dob }}</span></td>
                        <td>
                            <span class="responsible-matters-primary" title="{{ $activityText }}">{{ \Illuminate\Support\Str::limit($activityText, 72) }}</span>
                            <span class="responsible-matters-meta">{{ $matter->last_activity_display ?? '—' }}</span>
                        </td>
                        <td><span class="responsible-matters-date">{{ $matter->dashboard_created }}</span></td>
                        <td><span class="responsible-matters-primary">{{ $matter->dashboard_stage !== '' ? $matter->dashboard_stage : '—' }}</span></td>
                        <td><span class="responsible-matters-primary">{{ $matter->dashboard_legal_practitioner }}</span></td>
                        <td><span class="responsible-matters-primary">{{ $matter->dashboard_person_assisting }}</span></td>
                        <td class="responsible-matters-table__actions">
                            @if($detailUrl)
                                <a href="{{ $detailUrl }}" class="responsible-matters-open" title="Open matter">
                                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                    <span class="visually-hidden">Open matter</span>
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
