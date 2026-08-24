<!-- Activity Feed (Timeline tab; single #activity-feed instance) -->
<aside class="activity-feed activity-feed--simple" id="activity-feed">
    <div class="activity-feed-toolbar">
        <div class="activity-filters" role="toolbar" aria-label="Filter activity types">
            <button type="button" class="activity-filter-btn active" data-filter="all">All</button>
            <button type="button" class="activity-filter-btn" data-filter="activity">Activity</button>
            <button type="button" class="activity-filter-btn" data-filter="note">Notes</button>
            <button type="button" class="activity-filter-btn" data-filter="document">Documents</button>
            <button type="button" class="activity-filter-btn" data-filter="signature">Signatures</button>
            <button type="button" class="activity-filter-btn" data-filter="accounting">Accounting</button>
        </div>
        <div class="activity-feed-header-actions">
            <button type="button"
                    class="btn btn-sm btn-link p-0 activity-feed-expand-all"
                    id="activity-feed-expand-all"
                    title="Expand all details"
                    aria-pressed="false"
                    aria-label="Expand all activity details">
                <i class="fa-solid fa-angles-down" aria-hidden="true"></i>
                <span class="activity-feed-expand-all__label">Expand all</span>
            </button>
            <button type="button" class="btn btn-sm btn-link p-0 activity-feed-filter-toggle" id="activity-feed-filter-toggle" title="Show search" aria-expanded="false" aria-controls="activity-feed-filter-bar" hidden>
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-sm btn-link p-0 activity-feed-refresh" id="activity-feed-refresh" title="Refresh">
                <i class="fa-solid fa-rotate" aria-hidden="true"></i>
            </button>
            <label for="increase-activity-feed-width" class="activity-feed-expand-label">
                <input type="checkbox" id="increase-activity-feed-width" title="Expand Width">
                <span>Expand</span>
            </label>
        </div>
    </div>

    <!-- Extended Filters (search / date — closed by default) -->
    <div class="activity-feed-filter-bar" id="activity-feed-filter-bar" style="display: none;">
        <div class="activity-feed-filter-row">
            <input type="text"
                   class="form-control form-control-sm activity-feed-search"
                   id="activity-feed-search"
                   placeholder="Search timeline…"
                   autocomplete="off">
        </div>
        <div class="activity-feed-filter-row">
            <input type="text"
                   class="form-control form-control-sm activity-feed-date"
                   id="activity-feed-date-from"
                   placeholder="From"
                   autocomplete="off">
            <input type="text"
                   class="form-control form-control-sm activity-feed-date"
                   id="activity-feed-date-to"
                   placeholder="To"
                   autocomplete="off">
        </div>
        <div class="activity-feed-filter-actions">
            <button type="button" class="btn btn-sm btn-primary activity-feed-apply" id="activity-feed-apply">Apply</button>
            <button type="button" class="btn btn-sm btn-outline-secondary activity-feed-reset" id="activity-feed-reset">Reset</button>
        </div>
    </div>

    {{-- Keep legacy header hook for scripts that look up .activity-feed-header --}}
    <div class="activity-feed-header" hidden aria-hidden="true">
        <h2>Activity Feed</h2>
    </div>

    <ul class="feed-list">
        <li class="feed-item feed-item--loading" style="text-align: center; padding: 36px 20px; color: #5e7a90;">
            <p class="mb-0 small">Loading timeline…</p>
        </li>
    </ul>
</aside>
