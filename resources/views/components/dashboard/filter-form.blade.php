@props(['filters', 'workflowStages'])

@php
    $filtersActive = (! empty(trim((string) ($filters['client_name'] ?? ''))))
        || (! empty((string) ($filters['client_stage'] ?? '')));
@endphp

<div class="filter-toolbar">
    <div class="filter-controls">
        <form id="filterForm" method="GET" action="{{ route('dashboard') }}">
            <div class="search-box">
                <input type="text"
                       name="client_name"
                       placeholder="Search Client Name..."
                       value="{{ $filters['client_name'] ?? '' }}">
                <i class="fa-solid fa-search"></i>
            </div>

            <select name="client_stage" class="stage-select">
                <option value="">All Stages</option>
                @foreach($workflowStages as $stage)
                    <option value="{{ $stage['id'] }}"
                            {{ (isset($filters['client_stage']) && $filters['client_stage'] == $stage['id']) ? 'selected' : '' }}>
                        {{ $stage['name'] }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="filter-button{{ $filtersActive ? ' filter-button--active' : '' }}">
                <i class="fa-solid fa-filter"></i> Filter
                @if($filtersActive)
                    <span class="filter-active-dot" aria-hidden="true"></span>
                @endif
            </button>

            @if($filtersActive)
                <a href="{{ route('dashboard') }}" class="clear-filters">
                    <i class="fa-solid fa-times"></i> Clear Filters
                </a>
            @endif
        </form>
    </div>
</div>
