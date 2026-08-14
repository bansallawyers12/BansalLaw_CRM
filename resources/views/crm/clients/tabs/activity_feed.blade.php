<!-- Activity Feed (Timeline tab; single #activity-feed instance) -->
<aside class="activity-feed" id="activity-feed">
    <div class="activity-feed-header">
        <h2><i class="fa-solid fa-history" aria-hidden="true"></i> Activity Feed</h2>
        <div class="activity-feed-header-actions">
            <button type="button" class="btn btn-sm btn-link p-0 activity-feed-filter-toggle" id="activity-feed-filter-toggle" title="Hide filters" aria-expanded="true" aria-controls="activity-feed-filter-bar" hidden>
                <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
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

    <!-- Extended Filters (visible on Timeline tab; toggleable) -->
    <div class="activity-feed-filter-bar" id="activity-feed-filter-bar" style="display: none;">
        <div class="activity-feed-filter-row">
            <input type="text"
                   class="form-control form-control-sm activity-feed-search"
                   id="activity-feed-search"
                   placeholder="Search activities..."
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
            <button type="button" class="btn btn-sm btn-primary activity-feed-apply" id="activity-feed-apply">
                <i class="fa-solid fa-search" aria-hidden="true"></i> Apply
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary activity-feed-reset" id="activity-feed-reset">
                <i class="fa-solid fa-arrow-rotate-right" aria-hidden="true"></i> Reset
            </button>
        </div>
    </div>

    <!-- Activity Type Filters -->
    <div class="activity-filters" role="toolbar" aria-label="Filter activity types">
        <button type="button" class="activity-filter-btn active" data-filter="all">
            <i class="fa-solid fa-list" aria-hidden="true"></i> All
        </button>
        <button type="button" class="activity-filter-btn" data-filter="activity">
            <i class="fa-solid fa-bolt" aria-hidden="true"></i> Activity
        </button>
        <button type="button" class="activity-filter-btn" data-filter="note">
            <i class="fa-solid fa-note-sticky" aria-hidden="true"></i> Notes
        </button>
        <button type="button" class="activity-filter-btn" data-filter="document">
            <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Documents
        </button>
        <button type="button" class="activity-filter-btn" data-filter="signature">
            <i class="fa-solid fa-file-signature" aria-hidden="true"></i> Signatures
        </button>
        <button type="button" class="activity-filter-btn" data-filter="accounting">
            <i class="fa-solid fa-dollar-sign" aria-hidden="true"></i> Accounting
        </button>
    </div>

    <ul class="feed-list">
        <li class="feed-item feed-item--loading" style="text-align: center; padding: 36px 20px; color: #5e7a90;">
            <p class="mb-0 small">Loading timeline…</p>
        </li>
    </ul>
</aside>
