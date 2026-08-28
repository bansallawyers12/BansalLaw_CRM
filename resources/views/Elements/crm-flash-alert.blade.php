@php
    $type = $type ?? 'info';
    $alertType = $type === 'error' ? 'danger' : $type;
    $icons = [
        'success' => 'fa-circle-check',
        'danger' => 'fa-circle-xmark',
        'warning' => 'fa-triangle-exclamation',
        'info' => 'fa-circle-info',
    ];
    $defaultTitles = [
        'success' => 'Success',
        'danger' => 'Error',
        'warning' => 'Warning',
        'info' => 'Information',
    ];
    $icon = $icons[$alertType] ?? $icons['info'];
    $heading = $title ?? ($defaultTitles[$alertType] ?? 'Notice');
    $autoDismiss = $autoDismiss ?? 6000;
@endphp
<div class="alert crm-flash crm-flash-{{ $alertType }} alert-{{ $alertType }} alert-dismissible fade show" role="alert" @if($autoDismiss) data-auto-dismiss="{{ $autoDismiss }}" @endif>
    <div class="crm-flash__inner">
        <div class="crm-flash__icon" aria-hidden="true">
            <i class="fa-solid {{ $icon }}"></i>
        </div>
        <div class="crm-flash__content">
            <div class="crm-flash__title">{{ $heading }}</div>
            <div class="crm-flash__message">{!! $message !!}</div>
        </div>
        <button type="button" class="btn-close crm-flash__close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
