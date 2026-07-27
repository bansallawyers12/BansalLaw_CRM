@extends('layouts.crm_client_detail')
@section('title', 'Clients Archived')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-flatpickr.css') }}">
<link rel="stylesheet" href="{{ asset('css/clients-index.css') }}">
<style>
    .clients-swal-popup {
        border-radius: 14px !important;
        border: 1px solid var(--border, #c8dcef) !important;
        box-shadow: 0 16px 48px rgba(30, 61, 96, 0.18) !important;
        padding: 0.5rem 0 1.5rem !important;
    }

    .clients-swal-popup .swal2-title {
        color: var(--navy, #1e3d60) !important;
        font-size: 1.2rem !important;
        font-weight: 700 !important;
    }

    .clients-swal-popup .swal2-html-container {
        color: var(--text-muted, #5e7a90) !important;
        font-size: 0.9375rem !important;
        line-height: 1.5 !important;
    }
</style>
@endsection

@section('content')
<div id="clients-listing-spa-root" class="listing-container clients-listing clients-listing--archived" data-spa-root="1">
    <section class="listing-section">
        <div class="listing-section-body" id="clients-listing-spa-inner">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg">
                </div>
                <div class="card-header">
                    <div class="clients-page-header">
                        <div class="clients-page-header__title">
                            <span class="clients-page-header__icon" aria-hidden="true">
                                <i class="fa-solid fa-box-archive"></i>
                            </span>
                            <div>
                                <h4>Archived Clients</h4>
                                <p class="clients-page-header__subtitle">
                                    {{ number_format($lists->total()) }} archived {{ Str::plural('record', $lists->total()) }} &middot; Restore or review inactive client and lead records
                                </p>
                            </div>
                        </div>

                        <div class="card-header-actions">
                            <div class="per-page-wrap">
                                <label for="per_page">Show</label>
                                <select name="per_page" id="per_page" class="form-control per-page-select" aria-label="Results per page">
                                    @foreach([10, 20, 50, 100, 200] as $option)
                                        <option value="{{ $option }}" {{ ($perPage ?? 20) == $option ? 'selected' : '' }}>
                                            {{ $option }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="clients-toolbar">
                        <ul class="clients-tabs nav" id="client_tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link" id="clients-tab" href="{{ URL::to('/clients') }}">Clients</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" id="archived-tab" href="{{ URL::to('/archived') }}">Archived</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="lead-tab" href="{{ URL::to('/leads') }}">Leads</a>
                            </li>
                        </ul>
                        <span class="clients-select-help"><i class="fa-regular fa-square-check"></i> Select records with the checkboxes on the left</span>
                    </div>

                    <div class="clients-bulk-bar" id="clientsBulkBar" aria-live="polite">
                        <span class="clients-bulk-bar__count">
                            <i class="fa-solid fa-check-double"></i>
                            <span id="selectedCount">0</span> selected
                        </span>
                    </div>

                    @if($lists->total() > 0)
                    <div class="clients-results-bar">
                        Showing {{ number_format($lists->firstItem()) }}&ndash;{{ number_format($lists->lastItem()) }} of {{ number_format($lists->total()) }} archived records
                    </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-archived">
                            <thead>
                                <tr>
                                    <th class="client-select-cell">
                                        <label class="client-row-checkbox client-row-checkbox--header" for="checkbox-all" title="Select all on this page">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="cb-select-all" id="checkbox-all">
                                            <span class="client-row-checkbox__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                        </label>
                                    </th>
                                    <th>Name</th>
                                    <th>Assignee</th>
                                    <th>City</th>
                                    <th>Archived</th>
                                    <th>Added On</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if($lists->count() > 0)
                                <?php $i = 0; ?>
                                @foreach ($lists as $list)
                                    <?php
                                    $encodedId = base64_encode(convert_uuencode(@$list->id));
                                    $clientDetailUrl = URL::to('/clients/detail/' . $encodedId);
                                    $firstName = trim((string) @$list->first_name);
                                    $lastName = trim((string) @$list->last_name);
                                    $displayName = trim($firstName . ' ' . $lastName);
                                    if ($displayName === '') {
                                        $displayName = config('constants.empty');
                                    }
                                    $initials = strtoupper(substr($firstName !== '' ? $firstName : $lastName, 0, 1) . substr($lastName !== '' ? $lastName : $firstName, 0, 1));
                                    if ($initials === '') {
                                        $initials = '?';
                                    }
                                    $assignee = \App\Models\Staff::where('id', @$list->assignee)->first();
                                    $assigneeName = $assignee
                                        ? trim(($assignee->first_name ?? '') . ' ' . ($assignee->last_name ?? ''))
                                        : config('constants.empty');
                                    $archivedOn = ! empty($list->archived_on) ? \Carbon\Carbon::parse($list->archived_on) : null;
                                    $addedOn = ! empty($list->created_at) ? \Carbon\Carbon::parse($list->created_at) : null;
                                    $typeLabel = ($list->type ?? 'client') === 'lead' ? 'Lead' : 'Client';
                                    ?>
                                    <tr id="id_{{ $list->id }}" class="client-data-row">
                                        <td class="client-select-cell">
                                            <label class="client-row-checkbox" for="checkbox-{{ $i }}" title="Select record">
                                                <input data-id="{{ @$list->id }}" type="checkbox" data-checkboxes="mygroup" class="cb-element your-checkbox" id="checkbox-{{ $i }}">
                                                <span class="client-row-checkbox__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="client-name-cell">
                                                <span class="client-avatar" aria-hidden="true">{{ $initials }}</span>
                                                <div class="client-name-meta">
                                                    <a href="{{ $clientDetailUrl }}" class="client-name-link" title="View profile">{{ Str::limit($displayName, 50, '...') }}</a>
                                                    @if(!empty($list->client_id))
                                                        <span class="client-id-chip">{{ Str::limit($list->client_id, 24, '...') }}</span>
                                                    @endif
                                                    <span class="status-badge inactive archived-type-badge">{{ $typeLabel }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ Str::limit($assigneeName, 40, '...') }}</td>
                                        <td>{{ ! empty($list->city) ? Str::limit($list->city, 40, '...') : config('constants.empty') }}</td>
                                        <td class="archived-date-cell">
                                            @if($archivedOn)
                                                <span class="lead-contact-date__date">{{ $archivedOn->format('d/m/Y') }}</span>
                                                <span class="lead-contact-date__time">{{ $archivedOn->format('g:i a') }}</span>
                                            @else
                                                {{ config('constants.empty') }}
                                            @endif
                                        </td>
                                        <td class="archived-date-cell">
                                            @if($addedOn)
                                                <span class="lead-contact-date__date">{{ $addedOn->format('d/m/Y') }}</span>
                                            @else
                                                {{ config('constants.empty') }}
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button type="button"
                                                        class="btn-action-icon btn-action-edit"
                                                        title="Unarchive record"
                                                        onclick="unarchiveArchivedClient({{ $list->id }}, {{ json_encode($displayName) }})">
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                </button>
                                                <a class="btn-action-icon btn-action-email"
                                                   href="{{ $clientDetailUrl }}"
                                                   title="View record">
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $i++; ?>
                                @endforeach
                                @else
                                    <tr>
                                        <td colspan="7">
                                            <div class="clients-empty-state">
                                                <div class="clients-empty-state__icon" aria-hidden="true">
                                                    <i class="fa-solid fa-box-open"></i>
                                                </div>
                                                <h5>No archived records</h5>
                                                <p>There are no archived client or lead records to display.</p>
                                                <a href="{{ URL::to('/clients') }}" class="btn btn-theme btn-theme-sm">
                                                    <i class="fa-solid fa-users"></i> Back to Clients
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        {!! $lists->appends(\Request::except('page'))->render() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/crm/clients/clients-listing-spa.js') }}"></script>
<script>
window.ClientsListingSpaConfig = {
    csrfToken: document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '',
    routes: {
        clientsIndex: @json(url('/clients')),
        leadsIndex: @json(url('/leads')),
        archivedIndex: @json(url('/archived')),
        unarchive: @json(url('/unarchive')),
        mergeRecords: @json(url('/merge_records')),
        getRecipients: @json(url('/clients/get-recipients')),
        getTemplates: @json(url('/get-templates'))
    }
};

jQuery(function () {
    if (window.ClientsListingSpa) {
        ClientsListingSpa.init();
    }
});
</script>
@endpush
