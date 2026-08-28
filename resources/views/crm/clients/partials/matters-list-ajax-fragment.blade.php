{{-- Lightweight HTML for infinite-scroll AJAX (no layout / scripts). --}}
@php
    $listTab = $listTab ?? 'active';
    $currentPage = method_exists($lists, 'currentPage') ? $lists->currentPage() : 1;
    $lastPage = method_exists($lists, 'lastPage') ? $lists->lastPage() : 1;
@endphp
<div id="matters-listing-root"
     class="listing-container matters-listing"
     data-infinite-scroll="1"
     data-current-page="{{ $currentPage }}"
     data-last-page="{{ $lastPage }}"
     data-per-page="20"
     data-list-tab="{{ $listTab }}">
    @include('crm.clients.partials.matters-list-spa-body')
</div>
