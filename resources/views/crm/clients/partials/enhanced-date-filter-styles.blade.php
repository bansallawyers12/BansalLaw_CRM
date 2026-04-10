<!-- Enhanced Date Filter Styles — docs/theme.md (tokens from crm-theme.css) -->
<style>
    .date-filter-section {
        background: var(--card-bg, #ffffff);
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
        border: 1px solid var(--border, #c8dcef);
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
    }

    .date-filter-section h5 {
        color: var(--navy, #1e3d60);
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 16px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .date-filter-section h5 i {
        color: var(--sidebar-active, #3a6fa8);
        font-size: 16px;
    }

    .quick-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .quick-filter-chip {
        background: var(--card-bg, #ffffff);
        border: 2px solid var(--border, #c8dcef);
        border-radius: 20px;
        padding: 8px 18px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-muted, #5e7a90);
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .quick-filter-chip:hover {
        border-color: var(--sidebar-active, #3a6fa8);
        color: var(--navy, #1e3d60);
        box-shadow: 0 2px 8px rgba(30, 61, 96, 0.08);
    }

    .quick-filter-chip.active {
        background: var(--navy, #1e3d60);
        border-color: var(--navy, #1e3d60);
        color: #fff;
        box-shadow: 0 2px 8px rgba(30, 61, 96, 0.15);
    }

    .quick-filter-chip i {
        font-size: 12px;
    }

    .date-range-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .date-range-wrapper .form-group {
        margin-bottom: 0;
        flex: 1;
        min-width: 200px;
    }

    .date-range-arrow {
        color: var(--text-muted, #5e7a90);
        font-size: 18px;
        font-weight: 700;
        margin: 0 8px;
    }

    .fy-selector {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .fy-selector label {
        margin-bottom: 0 !important;
        white-space: nowrap;
    }

    .fy-selector .form-control {
        max-width: 250px;
    }

    .active-filters-badge {
        background: rgba(30, 122, 82, 0.12);
        color: var(--success, #1e7a52);
        border: 1px solid rgba(30, 122, 82, 0.35);
        border-radius: 12px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 8px;
    }

    button.clear-filter-btn,
    a.clear-filter-btn {
        background: transparent;
        border: 2px solid var(--danger, #a83020);
        color: var(--danger, #a83020);
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none !important;
        box-sizing: border-box;
    }

    a.clear-filter-btn:hover,
    a.clear-filter-btn:focus,
    a.clear-filter-btn:active {
        text-decoration: none !important;
    }

    .clear-filter-btn i,
    .clear-filter-btn .fas,
    .clear-filter-btn .fa {
        color: inherit;
    }

    .clear-filter-btn:hover,
    .clear-filter-btn:focus {
        background: var(--danger, #a83020);
        border-color: var(--danger, #a83020);
        color: #fff !important;
        text-decoration: none !important;
    }

    .clear-filter-btn:hover i,
    .clear-filter-btn:focus i,
    .clear-filter-btn:hover .fas,
    .clear-filter-btn:focus .fas,
    .clear-filter-btn:hover .fa,
    .clear-filter-btn:focus .fa {
        color: #fff !important;
    }

    .divider-text {
        color: var(--text-muted, #5e7a90);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 16px 0 12px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .divider-text::before,
    .divider-text::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border, #c8dcef);
    }
</style>
