@php
    $total = (int) ($totalData ?? 0);
    $loaded = (int) ($lists->lastItem() ?? 0);
    $currentPage = (int) ($lists->currentPage() ?? 1);
    $lastPage = max(1, (int) ($lists->lastPage() ?? 1));
    $hasMore = $currentPage < $lastPage;
@endphp
<div id="mat-list-scroll-status"
    class="mat-list-scroll-status d-flex flex-wrap align-items-center justify-content-between gap-3 w-100"
    data-current-page="{{ $currentPage }}"
    data-last-page="{{ $lastPage }}"
    data-total="{{ $total }}"
    data-loaded="{{ $loaded }}"
    data-has-more="{{ $hasMore ? '1' : '0' }}">
    <div class="mat-list-scroll-status__summary text-muted small">
        @if($total > 0)
            Showing <span data-mat-loaded-count>{{ $loaded }}</span> of {{ $total }} matters
        @else
            No matters found
        @endif
    </div>
    <div class="mat-list-scroll-status__meta text-muted small">
        @if($total > 0)
            <span class="mat-list-scroll-status__hint{{ $hasMore ? '' : ' d-none' }}" data-scroll-more-hint>Scroll for more</span>
            <span class="mat-list-scroll-status__end{{ $hasMore ? ' d-none' : '' }}" data-scroll-end-hint>All matters loaded</span>
        @endif
    </div>
</div>
