<!-- Enhanced Date Filter Styles -->
<style>
    /* Date Filter Section */
    .date-filter-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        border: 2px solid #e2e8f0;
    }

    .date-filter-section h5 {
        color: #1e293b;
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
        color: #667eea;
        font-size: 16px;
    }

    /* Quick Filter Chips */
    .quick-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .quick-filter-chip {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 20px;
        padding: 8px 18px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .quick-filter-chip:hover {
        border-color: #667eea;
        color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }

    .quick-filter-chip.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .quick-filter-chip i {
        font-size: 12px;
    }

    /* Date Range Inputs */
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
        color: #94a3b8;
        font-size: 18px;
        font-weight: 700;
        margin: 0 8px;
    }

    /* Financial Year Selector */
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

    /* Active Filter Badge */
    .active-filters-badge {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-radius: 12px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 8px;
    }

    /* Clear Filters Button */
    .clear-filter-btn {
        background: transparent;
        border: 2px solid #ef4444;
        color: #ef4444;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .clear-filter-btn:hover {
        background: #ef4444;
        color: white;
        transform: translateY(-2px);
    }

    .divider-text {
        color: #94a3b8;
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
        background: #e2e8f0;
    }
</style>

