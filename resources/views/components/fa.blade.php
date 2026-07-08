@props([
    'icon',
    'style' => 'solid',
    'spin' => false,
])

@php
    $spinActive = filter_var($spin, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
@endphp

<i
    aria-hidden="true"
    {{ $attributes->class([\App\Helpers\FontAwesomeHelper::iconClass($style, $icon, $spinActive)]) }}
></i>
