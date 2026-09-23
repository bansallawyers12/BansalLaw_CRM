@props([
    'title',
    'count',
    'icon' => 'fa-solid fa-chart-bar',
    'iconClass' => 'icon-active',
    'route' => null,
    'color' => 'primary',
    'subtitle' => null,
    'dataKpi' => null,
])

@php
    /* docs/theme.md — Icon Dot Colours (KPI icons) */
    $iconWrapperClass = in_array($iconClass, ['icon-active', 'icon-pending', 'icon-success', 'icon-closed'], true)
        ? $iconClass
        : 'icon-active';
    $cardModifier = str_replace('icon-', '', $iconWrapperClass);
    $tag = $route ? 'a' : 'div';

    $pillLabel = match($dataKpi) {
        'active_matters' => 'Open',
        'closed_matters' => 'Archived',
        'note_deadline' => 'Urgent',
        'recent_activity' => 'Active',
        default => null,
    };
@endphp

<{{ $tag }}
    @if($route) href="{{ $route }}" @endif
    class="kpi-card-modern kpi-card-modern--{{ $cardModifier }}{{ $route ? ' kpi-card-modern--link' : '' }}"
    @if($dataKpi) data-kpi-key="{{ $dataKpi }}" @endif
    @if($route) aria-label="{{ $title }} — {{ number_format($count) }}" @endif
>
    <div class="kpi-card-inner">
        <div class="kpi-card-top">
            <div class="kpi-icon-wrapper {{ $iconWrapperClass }}">
                <i class="{{ $icon }}"></i>
            </div>
            @if($pillLabel)
                <span class="kpi-tag kpi-tag--{{ $cardModifier }}">{{ $pillLabel }}</span>
            @endif
        </div>
        <div class="kpi-content">
            <h3 class="kpi-title">{{ $title }}</h3>
            <div class="kpi-count">
                <span class="kpi-count-number" @if($dataKpi) data-kpi-value="{{ $dataKpi }}" @endif>{{ number_format($count) }}</span>
            </div>
            @if($subtitle)
                <div class="kpi-footer">
                    <p class="kpi-subtitle" @if($dataKpi) data-kpi-subtitle="{{ $dataKpi }}" @endif>{{ $subtitle }}</p>
                    @if($route)
                        <i class="fa-solid fa-arrow-right kpi-arrow" aria-hidden="true"></i>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <div class="kpi-card-shine"></div>
</{{ $tag }}>

