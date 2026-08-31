{{-- Placeholder pane for tabs not SSR'd on first paint; filled via ClientTabLazy + /clients/detail-tab/... --}}
<div class="tab-pane" id="{{ $tabSlug }}-tab" data-lazy-tab="{{ $tabSlug }}" data-loaded="0" aria-hidden="true">
    <div class="client-tab-lazy-placeholder text-center py-5" role="status">
        <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
        <p class="text-muted mb-0 mt-2">Loading…</p>
    </div>
</div>
