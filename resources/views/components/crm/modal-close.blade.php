@props([
    'dismiss' => true,
    'onclick' => null,
    'label' => 'Close',
])

<button
    type="button"
    {{ $attributes->merge(['class' => 'crm-modal-close']) }}
    @if($dismiss) data-bs-dismiss="modal" @endif
    @if($onclick) onclick="{{ $onclick }}" @endif
    aria-label="{{ $label }}"
>
    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
</button>
