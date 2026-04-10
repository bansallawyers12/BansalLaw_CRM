@props([
    'title',
    'count',
    'icon' => 'fas fa-chart-bar',
    'iconClass' => 'icon-active',
    'route' => null,
    'color' => 'primary'
])

@php
    /* docs/theme.md — Icon Dot Colours (KPI icons) */
    $iconWrapperClass = in_array($iconClass, ['icon-active', 'icon-pending', 'icon-success'], true)
        ? $iconClass
        : 'icon-active';
@endphp

<div class="kpi-card-modern kpi-card-modern--{{ str_replace('icon-', '', $iconWrapperClass) }}">
    <div class="kpi-card-inner">
        <div class="kpi-icon-wrapper {{ $iconWrapperClass }}">
            <i class="{{ $icon }}"></i>
        </div>
        <div class="kpi-content">
            <h3 class="kpi-title">{{ $title }}</h3>
            <div class="kpi-count">
                @if($route)
                    <a href="{{ $route }}" class="kpi-count-link">{{ number_format($count) }}</a>
                @else
                    <span class="kpi-count-number">{{ number_format($count) }}</span>
                @endif
            </div>
        </div>
    </div>
    <div class="kpi-card-shine"></div>
</div>

<style>
.kpi-card-modern {
    position: relative;
    background: white;
    border-radius: 12px;
    padding: 20px 18px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
    cursor: pointer;
}

.kpi-card-modern:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    border-color: rgba(0, 0, 0, 0.1);
}

.kpi-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--navy, #1e3d60), var(--sidebar-active, #3a6fa8));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.kpi-card-modern--pending::before {
    background: linear-gradient(90deg, var(--accent-gold, #c8992a), #9a7619);
}

.kpi-card-modern--success::before {
    background: linear-gradient(90deg, var(--success, #1e7a52), #155a3c);
}

.kpi-card-modern:hover::before {
    opacity: 1;
}

.kpi-card-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;
    z-index: 1;
}

.kpi-icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.kpi-card-modern:hover .kpi-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
}

.kpi-icon-wrapper i {
    font-size: 1.8em;
    transition: all 0.3s ease;
}

/* theme.md — Icon Dot Colours */
.kpi-icon-wrapper.icon-active {
    background: rgba(30, 61, 96, 0.1);
}
.kpi-icon-wrapper.icon-active i {
    color: var(--navy, #1e3d60);
}

.kpi-icon-wrapper.icon-pending {
    background: rgba(200, 153, 42, 0.12);
}
.kpi-icon-wrapper.icon-pending i {
    color: var(--accent-gold, #c8992a);
}

.kpi-icon-wrapper.icon-success {
    background: rgba(30, 122, 82, 0.12);
}
.kpi-icon-wrapper.icon-success i {
    color: var(--success, #1e7a52);
}

.kpi-content {
    width: 100%;
}

.kpi-title {
    margin: 0 0 10px 0;
    font-size: 0.8em;
    color: var(--text-muted, #5e7a90);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.kpi-count {
    margin: 0;
}

.kpi-count-number,
.kpi-count-link {
    font-size: 2.2em;
    font-weight: 800;
    color: var(--text-dark, #1a2c40);
    line-height: 1;
    display: inline-block;
    transition: all 0.3s ease;
    text-decoration: none;
}

.kpi-count-link:hover {
    transform: scale(1.05);
    color: var(--sidebar-active, #3a6fa8);
}

/* Shine effect on hover */
.kpi-card-shine {
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent 30%,
        rgba(255, 255, 255, 0.3) 50%,
        transparent 70%
    );
    transform: translateX(-100%);
    transition: transform 0.6s ease;
}

.kpi-card-modern:hover .kpi-card-shine {
    transform: translateX(100%);
}

@media (max-width: 768px) {
    .kpi-card-modern {
        padding: 18px 15px;
    }
    
    .kpi-icon-wrapper {
        width: 50px;
        height: 50px;
    }
    
    .kpi-icon-wrapper i {
        font-size: 1.5em;
    }
    
    .kpi-count-number,
    .kpi-count-link {
        font-size: 1.8em;
    }
}
</style>
