@php
    $perPage = (int) ($perPage ?? config('constants.limit', 20));
    $total = (int) ($totalData ?? 0);
    $from = $lists->firstItem();
    $to = $lists->lastItem();
    $currentPage = $lists->currentPage();
    $lastPage = max(1, $lists->lastPage());
@endphp
<div id="mat-list-pagination" class="mat-list-pagination d-flex flex-wrap align-items-center justify-content-between gap-3 w-100">
    <div class="mat-list-pagination__summary text-muted small">
        @if($total > 0)
            Showing {{ $from }}&ndash;{{ $to }} of {{ $total }} matters
        @else
            No matters found
        @endif
    </div>
    <div class="mat-list-pagination__controls d-flex flex-wrap align-items-center gap-3">
        @if($total > 0)
            <label class="mat-list-pagination__per-page small mb-0 d-flex align-items-center gap-1">
                <span>Show</span>
                <select id="mat-per-page-select" class="form-control form-control-sm" aria-label="Matters per page">
                    @foreach([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
                <span>per page</span>
            </label>
            @if($lastPage > 1)
                {!! $lists->appends(\Request::except('page'))->links() !!}
            @else
                <span class="small text-muted">Page {{ $currentPage }} of {{ $lastPage }}</span>
            @endif
        @endif
    </div>
</div>
