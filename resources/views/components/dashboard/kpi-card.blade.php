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
@endphp

<{{ $tag }}
    @if($route) href="{{ $route }}" @endif
    class="kpi-card-modern kpi-card-modern--{{ $cardModifier }}{{ $route ? ' kpi-card-modern--link' : '' }}"
    @if($dataKpi) data-kpi-key="{{ $dataKpi }}" @endif
    @if($route) aria-label="{{ $title }} — {{ number_format($count) }}" @endif
>
    <div class="kpi-card-inner">
        <div class="kpi-icon-wrapper {{ $iconWrapperClass }}">
            <i class="{{ $icon }}"></i>
        </div>
        <div class="kpi-content">
            <h3 class="kpi-title">{{ $title }}</h3>
            <div class="kpi-count">
                <span class="kpi-count-number" @if($dataKpi) data-kpi-value="{{ $dataKpi }}" @endif>{{ number_format($count) }}</span>
            </div>
            @if($subtitle)
                <p class="kpi-subtitle" @if($dataKpi) data-kpi-subtitle="{{ $dataKpi }}" @endif>{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    <div class="kpi-card-shine"></div>
</{{ $tag }}>
