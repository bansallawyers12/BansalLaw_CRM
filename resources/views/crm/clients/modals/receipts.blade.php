{{-- ========================================
    ALL RECEIPT-RELATED MODALS
    This file contains all receipt and financial reporting modals
    Total: 6 large modals for comprehensive financial management
    ======================================== --}}
@php
    $__receiptModalSolicitors = \App\Services\ClientEditService::staffSelectableForSolicitorRole();
    $__invoiceFeeEarners = \App\Support\InvoiceTimesheetLine::selectableFeeEarners();
@endphp

<style>
/* ============================================================================
   CLIENT FUNDS LEDGER - DRAG AND DROP ZONE STYLES
   ============================================================================ */

.ledger-drag-drop-zone {
    border: 2px dashed #ccc;
    border-radius: 6px;
    padding: 15px 12px;
    text-align: center;
    background-color: #f9f9f9;
    cursor: pointer !important;
    transition: all 0.3s ease;
    min-height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0;
    width: auto;
    min-width: 250px;
    max-width: 300px;
    position: relative;
    z-index: 1;
}

.ledger-drag-drop-zone:hover {
    border-color: #007bff;
    background-color: #f0f8ff;
    transform: translateY(-2px);
}

.ledger-drag-drop-zone.drag_over {
    border-color: #28a745;
    background-color: #e8f5e9;
    border-width: 3px;
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
}

.ledger-drag-drop-zone .drag-zone-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.ledger-drag-drop-zone .drag-zone-inner i {
    font-size: 28px;
    color: #2563eb;
    transition: all 0.3s ease;
}

.ledger-drag-drop-zone:hover .drag-zone-inner i {
    transform: scale(1.1);
    color: #0056b3;
}

.ledger-drag-drop-zone .drag-zone-content {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.ledger-drag-drop-zone .drag-zone-text {
    font-size: 13px;
    font-weight: 500;
    color: #333;
    margin: 0;
    line-height: 1.3;
}

.ledger-drag-drop-zone .drag-zone-formats {
    font-size: 11px;
    color: #4b5563;
    line-height: 1.2;
}

.ledger-drag-drop-zone.file-selected {
    border-color: #28a745;
    background-color: #f0fff4;
}

/* Selected Files Display */
.ledger-selected-files-display {
    padding: 8px;
    background-color: #e8f5e9;
    border-radius: 6px;
    border: 1px solid #c3e6cb;
    margin-bottom: 10px;
    max-width: 350px;
}

.ledger-selected-files-display .files-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 8px;
}

.ledger-selected-files-display .file-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    background-color: white;
    border-radius: 4px;
    font-size: 13px;
}

.ledger-selected-files-display .file-item i {
    color: #28a745;
}

.ledger-selected-files-display .file-item .file-name {
    flex: 1;
    color: #155724;
    word-break: break-word;
}

.ledger-selected-files-display .file-item .remove-file {
    padding: 0;
    margin: 0;
    line-height: 1;
    color: #dc3545;
    cursor: pointer;
}

.ledger-selected-files-display .file-item .remove-file:hover {
    opacity: 0.8;
}

.ledger-selected-files-display .remove-all-files {
    padding: 5px 10px;
    font-size: 12px;
}

/* Tax invoice — readable XXL layout */
.modal-xxl {
    --bs-modal-width: min(1480px, 96vw);
}
@media (min-width: 1200px) {
    .modal-xxl {
        --bs-modal-width: min(1480px, 96vw);
    }
}

#createreceiptmodal.invoice-entry-open {
    padding-bottom: 3.5rem !important;
}
#createreceiptmodal.invoice-entry-open .modal-dialog,
#createinvoicereceiptmodal .modal-dialog,
#createofficereceiptmodal .modal-dialog {
    max-width: min(1480px, 96vw) !important;
    width: 96vw !important;
    margin: 1.25rem auto 3.5rem auto !important;
    max-height: calc(100vh - 4.75rem) !important;
    height: auto !important;
    display: flex !important;
    flex-direction: column !important;
}
#createreceiptmodal.invoice-entry-open .modal-content,
#createinvoicereceiptmodal .modal-content,
#createofficereceiptmodal .modal-content {
    border: 0 !important;
    border-radius: 14px !important;
    box-shadow: 0 20px 45px -10px rgba(15, 39, 64, 0.22), 0 0 1px 1px rgba(15, 39, 64, 0.08) !important;
    overflow: hidden !important;
    max-height: calc(100vh - 4.75rem) !important;
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
}
#createreceiptmodal.invoice-entry-open .modal-header,
#createinvoicereceiptmodal .modal-header,
#createofficereceiptmodal .modal-header {
    flex-shrink: 0 !important;
    padding: 12px 20px !important;
}
#createreceiptmodal.invoice-entry-open .modal-body,
#createinvoicereceiptmodal .modal-body,
#createofficereceiptmodal .modal-body {
    overflow: hidden !important;
    padding: 10px 16px 14px 16px !important;
    display: flex !important;
    flex-direction: column !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
    height: 100% !important;
}
#createreceiptmodal.invoice-entry-open #client_receipt_form,
#createreceiptmodal.invoice-entry-open #office_receipt_form,
#createreceiptmodal.invoice-entry-open #invoice_receipt_form,
#createinvoicereceiptmodal #invoice_receipt_form,
#createofficereceiptmodal #office_receipt_form {
    flex-direction: column !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
    height: 100% !important;
    margin: 0 !important;
}
#createreceiptmodal.invoice-entry-open form[style*="none"] {
    display: none !important;
}
#createreceiptmodal.invoice-entry-open #client_receipt_form.active-entry-form,
#createreceiptmodal.invoice-entry-open #office_receipt_form.active-entry-form,
#createreceiptmodal.invoice-entry-open #invoice_receipt_form.active-entry-form,
#createinvoicereceiptmodal #invoice_receipt_form,
#createofficereceiptmodal #office_receipt_form {
    display: flex !important;
}
#createreceiptmodal.invoice-entry-open .invoice-form-shell,
#createinvoicereceiptmodal .invoice-form-shell,
#createofficereceiptmodal .invoice-form-shell,
#office_receipt_form .invoice-form-shell,
#client_receipt_form .invoice-form-shell {
    display: flex !important;
    flex-direction: column !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
    height: 100% !important;
}
#createreceiptmodal.invoice-entry-open .invoice-form-toolbar,
#createinvoicereceiptmodal .invoice-form-toolbar,
#office_receipt_form .invoice-form-toolbar,
#client_receipt_form .invoice-form-toolbar {
    flex-shrink: 0 !important;
    margin-bottom: 8px !important;
    padding: 8px 14px !important;
}
#createreceiptmodal.invoice-entry-open .invoice-timesheet-scroll,
#createinvoicereceiptmodal .invoice-timesheet-scroll,
#createofficereceiptmodal .invoice-timesheet-scroll,
#office_receipt_form .invoice-timesheet-scroll,
#client_receipt_form .invoice-timesheet-scroll {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    padding: 6px 8px 10px 8px !important;
}
.invoice-timesheet-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.invoice-timesheet-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.invoice-timesheet-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.invoice-timesheet-scroll::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

#invoice_receipt_form textarea.invoice-line-description,
#create_invoice_receipt textarea.invoice-line-description,
#adjust_invoice_receipt_form textarea.invoice-line-description,
#office_receipt_form textarea.invoice-line-description,
#create_office_receipt textarea.invoice-line-description {
    min-height: 48px;
    max-height: 140px;
    resize: vertical;
    line-height: 1.45;
    white-space: pre-wrap;
}

.invoice-form-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px 20px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    padding: 12px 18px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
}
.invoice-form-toolbar .form-group {
    margin-bottom: 0;
    min-width: 220px;
    flex: 1 1 240px;
}
.invoice-form-toolbar .form-group label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 4px;
    display: block;
}
.invoice-client-input-wrap {
    position: relative;
    display: flex;
    align-items: stretch;
}
.invoice-client-input-wrap .input-group-text {
    background: #ffffff;
    border-color: #cbd5e1;
    color: #64748b;
    padding: 0 10px;
    border-top-left-radius: 8px;
    border-bottom-left-radius: 8px;
}
.invoice-client-input-wrap .form-control {
    font-weight: 600;
    color: #1e293b;
    border-color: #cbd5e1;
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
    height: 36px;
    font-size: 0.88rem;
}
.invoice-form-toolbar .invoice-billing-mode-bar {
    flex: 2 1 320px;
    margin-bottom: 0;
}

.invoice-billing-mode-bar {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}
.invoice-billing-mode-bar__main {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.invoice-billing-mode-label {
    font-weight: 700;
    color: #334155;
    font-size: 0.82rem;
    letter-spacing: 0.01em;
    text-transform: uppercase;
}
.invoice-billing-mode-toggle {
    background: #e2e8f0;
    padding: 3px;
    border-radius: 24px;
    display: inline-flex;
    gap: 2px;
}
.invoice-billing-mode-toggle .btn {
    border: 0 !important;
    border-radius: 20px !important;
    padding: 4px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.2s ease-in-out;
    line-height: 1.4;
}
.invoice-billing-mode-toggle .btn-primary {
    background: #1e3d60 !important;
    color: #ffffff !important;
    box-shadow: 0 2px 5px rgba(30, 61, 96, 0.28);
}
.invoice-billing-mode-toggle .btn-outline-secondary {
    background: transparent !important;
    color: #64748b !important;
}
.invoice-billing-mode-toggle .btn-outline-secondary:hover {
    color: #1e293b !important;
}
.invoice-billing-mode-hint {
    font-size: 0.76rem;
    color: #64748b;
    line-height: 1.35;
    display: flex;
    align-items: center;
}

.invoice-timesheet-scroll {
    overflow-x: visible;
    overflow-y: auto;
    max-height: min(54vh, 520px);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    padding: 12px;
}
.invoice-timesheet-scroll > table {
    margin-bottom: 0 !important;
    border: 0 !important;
    width: 100%;
    min-width: 0;
    background: transparent;
}
.invoice-timesheet-scroll table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8fafc;
    color: #1e3d60;
    border: 0 !important;
    padding: 0 0 10px 0;
}
.invoice-timesheet-scroll table th,
.invoice-timesheet-scroll table td {
    vertical-align: top;
    white-space: normal;
    border: 0 !important;
    padding: 0;
}
.invoice-lines-legend__cell {
    background: transparent !important;
    border: 0 !important;
    padding: 0 0 10px 0 !important;
}
.invoice-lines-legend__wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 14px;
    background: #edf2f7;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.invoice-lines-legend__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: #1e3d60;
    color: #fff;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.invoice-lines-legend__title {
    display: block;
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
}
.invoice-lines-legend__hint {
    display: block;
    font-size: 0.74rem;
    font-weight: 500;
    color: #64748b;
    line-height: 1.2;
}

.invoice-line-block + .invoice-line-block td {
    padding-top: 12px;
}
.invoice-line-block__cell {
    width: 100%;
}
.invoice-line-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #1e3d60;
    border-radius: 12px;
    padding: 12px 16px 14px;
    box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.05), 0 1px 3px -1px rgba(15, 23, 42, 0.02);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.invoice-line-card:hover {
    border-color: #cbd5e1;
    border-left-color: #0d6efd;
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.08);
}
.invoice-line-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid #f1f5f9;
}
.invoice-line-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #475569;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.invoice-line-badge__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #3b82f6;
}
.invoice-line-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    color: #94a3b8;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    transition: all 0.2s ease;
    text-decoration: none;
    font-size: 0.76rem;
}
.invoice-line-remove:hover {
    background: #fee2e2;
    color: #dc2626;
    border-color: #fca5a5;
}

.invoice-line-card__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(115px, 1fr));
    gap: 10px 12px;
    align-items: flex-end;
}
.invoice-field {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.invoice-field__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 22px;
    margin-bottom: 5px;
    gap: 6px;
}
.invoice-field__label {
    margin: 0;
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    letter-spacing: 0.02em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.invoice-field__sub {
    font-weight: 500;
    color: #94a3b8;
    font-size: 0.67rem;
}
.invoice-date-mode-pills {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1px;
    display: inline-flex;
    gap: 1px;
}
.invoice-date-mode-pills .btn {
    padding: 1px 7px;
    font-size: 0.66rem;
    font-weight: 600;
    border-radius: 10px !important;
    border: none !important;
    line-height: 1.3;
}
.invoice-date-mode-pills .btn-primary {
    background: #1e3d60 !important;
    color: #ffffff !important;
}
.invoice-date-mode-pills .btn-outline-secondary {
    background: transparent !important;
    color: #64748b !important;
}
.invoice-input-icon-wrap,
.invoice-input-prefix-wrap {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    width: 100%;
}
.invoice-input-icon-wrap input,
.invoice-input-prefix-wrap input {
    width: 100%;
}
.invoice-input-icon-wrap .custom-error,
.invoice-input-prefix-wrap .custom-error,
.invoice-field .custom-error {
    width: 100%;
    margin-top: 2px;
    font-size: 11px;
    line-height: 1.2;
    display: block;
}
.invoice-input-icon {
    position: absolute;
    left: 9px;
    top: 9px;
    color: #94a3b8;
    font-size: 0.76rem;
    pointer-events: none;
    z-index: 2;
}
.invoice-input-icon-wrap input {
    padding-left: 28px !important;
}
.invoice-input-prefix {
    position: absolute;
    left: 9px;
    color: #64748b;
    font-weight: 600;
    font-size: 0.78rem;
    pointer-events: none;
    z-index: 2;
}
.invoice-input-prefix-wrap input {
    padding-left: 20px !important;
}
.invoice-timesheet-scroll .form-control,
.invoice-timesheet-scroll .form-select {
    height: 35px;
    font-size: 0.83rem;
    border-color: #cbd5e1;
    border-radius: 7px;
    background-color: #ffffff;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.invoice-timesheet-scroll .form-control:focus,
.invoice-timesheet-scroll .form-select:focus {
    border-color: #1e3d60;
    box-shadow: 0 0 0 3px rgba(30, 61, 96, 0.12);
}
.invoice-work-date[readonly],
.report_entry_date_fields_invoice[readonly],
.report_date_fields_office[readonly],
.report_entry_date_fields_office[readonly] {
    background-color: #ffffff !important;
    cursor: pointer;
}
.invoice-col-work-date { min-width: 175px; }
.invoice-col-recorded { min-width: 110px; }
.invoice-col-trans { min-width: 95px; }
.invoice-col-type { min-width: 135px; }
.invoice-col-earner { min-width: 130px; }
.invoice-col-role { min-width: 115px; }
.invoice-col-hrs { min-width: 65px; }
.invoice-col-rate { min-width: 92px; }
.invoice-col-amount { min-width: 100px; }
.invoice-col-gst { min-width: 82px; }

@media (min-width: 1200px) {
    .invoice-line-card__grid {
        grid-template-columns: 1.55fr 1.05fr 1.2fr 1.2fr 1.1fr 0.65fr 0.9fr 1fr 0.8fr;
    }
    .invoice-line-card__grid.has-trans-no {
        grid-template-columns: 1.45fr 1fr 0.9fr 1.15fr 1.15fr 1.05fr 0.6fr 0.85fr 0.95fr 0.75fr;
    }
}

.invoice-col-desc {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #f1f5f9;
}
.invoice-col-desc .invoice-line-description {
    height: auto;
    min-height: 48px;
    max-height: 140px;
    resize: vertical;
    font-size: 0.84rem;
    line-height: 1.45;
    border-radius: 7px;
    background: #fafbfc;
}
.invoice-col-desc .invoice-line-description:focus {
    background: #ffffff;
}

form.invoice-billing-mode-hourly .invoice-amount-ex-gst {
    background: #f1f5f9 !important;
    font-weight: 600;
    color: #1e293b;
}
form.invoice-billing-mode-fixed .invoice-hours-col,
form.invoice-billing-mode-fixed .invoice-col-rate {
    display: none;
}
form.invoice-billing-mode-fixed .invoice-billing-hint-hourly { display: none !important; }
form.invoice-billing-mode-fixed .invoice-billing-hint-fixed { display: inline !important; }
form.invoice-billing-mode-hourly .invoice-billing-hint-fixed { display: none !important; }

.invoice-form-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 8px !important;
    padding: 8px 16px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 -2px 10px -2px rgba(15, 23, 42, 0.04);
    flex-shrink: 0 !important;
}
.invoice-form-footer__left {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.invoice-add-line-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none !important;
}
.invoice-totals-card {
    display: inline-flex;
    align-items: center;
    gap: 16px;
    padding: 6px 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}
.invoice-totals-card__row {
    display: flex;
    align-items: baseline;
    gap: 8px;
    font-size: 0.82rem;
    color: #475569;
    white-space: nowrap;
}
.invoice-totals-card__row + .invoice-totals-card__row {
    position: relative;
    padding-left: 16px;
}
.invoice-totals-card__row + .invoice-totals-card__row::before {
    content: "";
    position: absolute;
    left: 0;
    top: 15%;
    height: 70%;
    width: 1px;
    background: #cbd5e1;
}
.invoice-totals-card__row--total {
    font-weight: 700;
    color: #1e3d60;
}
.invoice-totals-card__row--total .invoice-totals-card__value {
    font-size: 1.05rem;
    color: #1e3d60;
    font-weight: 800;
}
.invoice-totals-card__value {
    font-variant-numeric: tabular-nums;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-weight: 600;
    color: #1e293b;
}
.invoice-form-footer__actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.invoice-final-btn {
    background: linear-gradient(180deg, #1e3d60 0%, #152d47 100%) !important;
    border-color: #152d47 !important;
    box-shadow: 0 2px 6px rgba(30, 61, 96, 0.25);
    font-weight: 600;
}
.invoice-final-btn:hover {
    background: #152d47 !important;
    box-shadow: 0 4px 10px rgba(30, 61, 96, 0.35);
}

/* Office Receipt Line Grid */
.office-line-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px 14px;
    align-items: flex-end;
}
@media (min-width: 1200px) {
    .office-line-grid {
        grid-template-columns: 1.15fr 1.1fr 1.6fr 1.4fr 1.15fr;
    }
}
.office-col-trans-date { min-width: 140px; }
.office-col-entry-date { min-width: 135px; }
.office-col-invoice { min-width: 175px; }
.office-col-method { min-width: 165px; }
.office-col-amount { min-width: 135px; }

/* Quick Actions in Toolbar */
.office-quick-actions-bar {
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: flex-end;
}
.office-quick-actions-toggle {
    background: #e2e8f0;
    padding: 3px;
    border-radius: 24px;
    display: inline-flex;
    gap: 4px;
}
.office-quick-actions-toggle .btn {
    border: 0 !important;
    border-radius: 20px !important;
    padding: 4px 14px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569 !important;
    background: transparent !important;
    transition: all 0.2s ease-in-out;
    line-height: 1.4;
}
.office-quick-actions-toggle .btn:hover {
    background: #ffffff !important;
    color: #1e3d60 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Office Receipt Footer & Dropzone */
.office-form-footer {
    display: flex;
    flex-wrap: nowrap !important;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 8px !important;
    padding: 6px 14px !important;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    flex-shrink: 0 !important;
}
.office-form-footer__center {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 1 auto;
}
.office-footer-upload .office-drag-drop-zone {
    min-height: 32px !important;
    max-height: 36px !important;
    padding: 2px 10px !important;
    border: 1.5px dashed #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    min-width: 200px;
    max-width: 260px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 !important;
    box-sizing: border-box !important;
}
.office-footer-upload .office-drag-drop-zone:hover {
    border-color: #3b82f6;
    background: #f0f7ff;
}
.office-footer-upload .drag-zone-inner {
    flex-direction: row;
    align-items: center;
    gap: 8px;
}
.office-footer-upload .drag-zone-inner i {
    font-size: 15px;
    color: #2563eb;
}
.office-footer-upload .drag-zone-content {
    gap: 0;
    text-align: left;
}
.office-footer-upload .drag-zone-text {
    font-size: 11px;
    font-weight: 600;
    color: #334155;
    margin: 0;
    line-height: 1.2;
}
.office-footer-upload .drag-zone-formats {
    font-size: 9px;
    color: #64748b;
    line-height: 1.1;
}
.office-footer-upload .ledger-selected-files-display {
    max-width: 280px;
    margin-bottom: 0;
    padding: 4px 8px;
}

/* Trust Account Entry Specific Styles */
.trust-line-card {
    border-left: 4px solid #198754 !important;
}
.trust-line-card:hover {
    border-left-color: #157347 !important;
}
.trust-line-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(115px, 1fr));
    gap: 8px 12px;
    align-items: flex-end;
}
@media (min-width: 1200px) {
    .trust-line-grid {
        grid-template-columns: 1fr 1fr 1.35fr 1.15fr 1.15fr 1fr 1fr;
    }
}
.trust-col-trans-date { min-width: 120px; }
.trust-col-entry-date { min-width: 120px; }
.trust-col-type { min-width: 155px; }
.trust-col-invoice { min-width: 135px; }
.trust-col-method { min-width: 135px; }
.trust-col-receipt { min-width: 115px; }
.trust-col-payment { min-width: 115px; }

/* Trust Footer & Dropzone */
.trust-form-footer {
    display: flex;
    flex-wrap: nowrap !important;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 8px !important;
    padding: 6px 14px !important;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    flex-shrink: 0 !important;
}
.trust-form-footer__center {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 1 auto;
}
.trust-footer-upload .trust-drag-drop-zone {
    min-height: 32px !important;
    max-height: 36px !important;
    padding: 2px 10px !important;
    border: 1.5px dashed #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    min-width: 200px;
    max-width: 260px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 !important;
    box-sizing: border-box !important;
}
.trust-footer-upload .trust-drag-drop-zone:hover {
    border-color: #198754;
    background: #f0fff4;
}
.trust-footer-upload .drag-zone-inner {
    flex-direction: row !important;
    align-items: center !important;
    gap: 8px !important;
}
.trust-footer-upload .drag-zone-inner i {
    font-size: 14px !important;
    color: #198754;
    margin: 0 !important;
}
.trust-footer-upload .drag-zone-content {
    gap: 0 !important;
    text-align: left !important;
}
.trust-footer-upload .drag-zone-text {
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #334155;
    margin: 0 !important;
    line-height: 1.2 !important;
}
.trust-footer-upload .drag-zone-formats {
    font-size: 9px !important;
    color: #64748b;
    line-height: 1 !important;
    margin: 0 !important;
    display: block !important;
}
.trust-footer-upload .ledger-selected-files-display {
    max-width: 260px;
    margin-bottom: 0;
    padding: 2px 8px;
    font-size: 11px;
}
.trust-final-btn {
    background: linear-gradient(180deg, #198754 0%, #157347 100%) !important;
    border-color: #157347 !important;
    box-shadow: 0 2px 6px rgba(25, 135, 84, 0.25);
    font-weight: 600;
}
.trust-final-btn:hover {
    background: #157347 !important;
    box-shadow: 0 4px 10px rgba(25, 135, 84, 0.35);
}
#client_receipt_form textarea.invoice-line-description {
    min-height: 44px;
    max-height: 110px;
    resize: vertical;
    line-height: 1.4;
    font-size: 0.82rem;
    padding: 6px 10px;
}
</style>

{{-- 1. Create Receipt Modal (Multi-Type: Client Funds Ledger, Invoice, Office Receipt) --}}
<div class="modal fade custom_modal" id="createreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-xxl modal-dialog-scrollable" role="document">
		<div class="modal-content">
		  	<div class="modal-header">
				<h5 class="modal-title">Create Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
		    </div>

		  	<div class="modal-body receipt-modal-body">
				<!-- Radio Button Selection -->
				<div class="form-group receipt-type-selector">
			  		<label class="receipt-type-selector__label">Select entry type</label>
			  		<div class="receipt-type-selector__options">
			  		<label class="receipt-type-option">
						<input type="radio" name="receipt_type" value="client_receipt" checked>
						<span class="receipt-type-option__pill"><i class="fa-solid fa-building-columns"></i> Trust Account Entry</span>
			  		</label>

			  		<label class="receipt-type-option">
						<input type="radio" name="receipt_type" value="invoice_receipt">
						<span class="receipt-type-option__pill"><i class="fa-solid fa-file-invoice-dollar"></i> Tax Invoice</span>
			  		</label>

			  		<label class="receipt-type-option">
						<input type="radio" name="receipt_type" value="office_receipt">
						<span class="receipt-type-option__pill"><i class="fa-solid fa-hand-holding-dollar"></i> Office Receipt</span>
			  		</label>
			  		</div>
				</div>

				<!-- Trust Account Entry Form -->
				<form class="form-type trust-entry-form" method="post" action="{{URL::to('/clients/saveaccountreport')}}" name="client_receipt_form" autocomplete="off" id="client_receipt_form" enctype="multipart/form-data">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="1">
                    <input type="hidden" name="client_ledger_balance_amount" id="client_ledger_balance_amount" value="">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_ledger" value="">
					<div class="invoice-form-shell trust-form-shell">
						<div class="invoice-form-toolbar">
							<div class="form-group invoice-client-group">
								<label for="trust-entry-client-name">Client <span class="span_req">*</span></label>
								<div class="input-group input-group-sm invoice-client-input-wrap">
									<span class="input-group-text"><i class="fa-solid fa-user-tie"></i></span>
									<input id="trust-entry-client-name" type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="Client Name" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								</div>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>

							<div class="invoice-billing-mode-bar trust-quick-actions-bar">
								<div class="invoice-billing-mode-bar__main">
									<span class="invoice-billing-mode-label"><i class="fa-solid fa-building-columns me-1 text-success"></i> Trust Account</span>
									<span class="badge bg-light text-dark border ms-2 px-2 py-1"><i class="fa-solid fa-shield-halved text-success me-1"></i> Regulated Trust Account</span>
								</div>
								<p class="invoice-billing-mode-hint mb-0">
									<i class="fa-solid fa-circle-info text-info me-1"></i>
									<span>Record trust receipts, disbursements, fee transfers or client refunds</span>
								</p>
							</div>
						</div>

						<div class="invoice-timesheet-scroll">
							<table class="table text_wrap table-hover table-md vertical_align invoice-lines-table">
								<thead>
									<tr class="invoice-lines-legend">
										<th class="invoice-lines-legend__cell">
											<div class="invoice-lines-legend__wrap">
												<span class="invoice-lines-legend__icon" style="background:#198754;"><i class="fa-solid fa-list-ul"></i></span>
												<div class="invoice-lines-legend__content">
													<span class="invoice-lines-legend__title">Transaction lines</span>
													<span class="invoice-lines-legend__hint">Add one or more trust receipts, disbursements, fee transfers or refunds.</span>
												</div>
											</div>
										</th>
									</tr>
								</thead>
								<tbody class="productitem">
									<tr class="clonedrow invoice-line-block">
										<td class="invoice-line-block__cell">
											<div class="invoice-line-card trust-line-card">
												<div class="invoice-line-card__header">
													<div class="invoice-line-badge">
														<span class="invoice-line-badge__dot" style="background:#198754;"></span>
														<span class="invoice-line-badge__text">Transaction Line</span>
													</div>
													<a class="removeitems invoice-line-remove" href="javascript:;" title="Remove this line" aria-label="Remove line">
														<i class="fa-solid fa-trash-can"></i>
													</a>
												</div>

												<div class="invoice-line-card__grid trust-line-grid">
													<div class="invoice-field trust-col-trans-date">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Trans. date</label>
														</div>
														<div class="invoice-input-icon-wrap">
															<i class="fa-regular fa-calendar-days invoice-input-icon"></i>
															<input data-valid="required" class="form-control form-control-sm report_date_fields" name="trans_date[]" type="text" value="" placeholder="DD/MM/YYYY" readonly="readonly" title="Transaction date" />
														</div>
													</div>

													<div class="invoice-field trust-col-entry-date">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Entry date</label>
														</div>
														<div class="invoice-input-icon-wrap">
															<i class="fa-regular fa-clock invoice-input-icon"></i>
															<input data-valid="required" class="form-control form-control-sm report_entry_date_fields" name="entry_date[]" type="text" value="" readonly="readonly" title="Entry date" />
														</div>
													</div>

													<div class="invoice-field trust-col-type">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Transaction type</label>
														</div>
														<select class="form-select form-select-sm client_fund_ledger_type" name="client_fund_ledger_type[]" data-valid="required">
															<option value="">Select Type</option>
															<option value="Deposit" title="Money received into trust account on behalf of client">Trust Receipt (+)</option>
															<option value="Fee Transfer" title="Transfer from trust to office account for professional fees (requires invoice)">Transfer to Office (−)</option>
															<option value="Disbursement" title="Payment made from trust account on behalf of client (e.g. court fees, outlays)">Disbursement (Payment) (−)</option>
															<option value="Refund" title="Money returned to client from trust account">Refund to Client (−)</option>
														</select>
													</div>

													<div class="invoice-field trust-col-invoice">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Invoice Ref.</label>
														</div>
														<div class="trust-entry-invoice-cell">
															<span class="ledger-invoice-placeholder trust-entry-placeholder">—</span>
															<select class="form-select form-select-sm invoice_no_cls" name="invoice_no[]" style="display:none;">
															</select>
														</div>
													</div>

													<div class="invoice-field trust-col-method">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Payment method</label>
														</div>
														<div class="trust-entry-cell-stack">
															<select class="form-select form-select-sm ledger-payment-method" name="payment_method[]">
																<option value="">—</option>
																<option value="Cash">Cash</option>
																<option value="Bank transfer">Bank Transfer / EFT</option>
																<option value="EFTPOS">EFTPOS / Card</option>
																<option value="Cheque">Cheque</option>
																<option value="Refund">Refund</option>
															</select>
															<div class="ledger-eftpos-surcharge-block" style="display:none;margin-top:4px;">
																<label class="text-muted" style="font-size:11px;margin:0;display:block;">Card surcharge ($)</label>
																<input type="text" class="form-control form-control-sm ledger-eftpos-surcharge-input" name="eftpos_surcharge_amount[]" inputmode="decimal" autocomplete="off" placeholder="0.00" style="font-size:12px;padding:3px 8px;" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="">
															</div>
														</div>
													</div>

													<div class="invoice-field trust-col-receipt">
														<div class="invoice-field__header">
															<label class="invoice-field__label text-success">Receipt (+)</label>
														</div>
														<div class="invoice-input-prefix-wrap">
															<span class="invoice-input-prefix">$</span>
															<input class="form-control form-control-sm deposit_amount_per_row" name="deposit_amount[]" type="text" inputmode="decimal" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" readonly />
														</div>
													</div>

													<div class="invoice-field trust-col-payment">
														<div class="invoice-field__header">
															<label class="invoice-field__label text-danger">Payment (−)</label>
														</div>
														<div class="invoice-input-prefix-wrap">
															<span class="invoice-input-prefix">$</span>
															<input class="form-control form-control-sm withdraw_amount_per_row" name="withdraw_amount[]" type="text" inputmode="decimal" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" readonly />
														</div>
													</div>
												</div>

												<div class="invoice-field invoice-col-desc mt-2">
													<div class="invoice-field__header">
														<label class="invoice-field__label"><i class="fa-regular fa-message text-muted me-1"></i> Particulars / Description</label>
													</div>
													<textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="2" placeholder="Describe the transaction particulars or description (e.g. client funds received into trust, court filing fee, settlement funds)..."></textarea>
												</div>
											</div>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="invoice-form-footer trust-form-footer">
							<div class="invoice-form-footer__left">
								<a href="javascript:;" class="btn btn-sm btn-outline-primary openproductrinfo trust-entry-add-line invoice-add-line-btn">
									<i class="fa-solid fa-plus me-1"></i> Add line
								</a>
								<div class="invoice-totals-card" aria-live="polite">
									<div class="invoice-totals-card__row">
										<span class="invoice-totals-card__label"><i class="fa-solid fa-arrow-down text-success me-1"></i> Receipts:</span>
										<span class="invoice-totals-card__value text-success total_deposit_amount_all_rows">$0.00</span>
									</div>
									<div class="invoice-totals-card__row">
										<span class="invoice-totals-card__label"><i class="fa-solid fa-arrow-up text-danger me-1"></i> Payments:</span>
										<span class="invoice-totals-card__value text-danger total_withdraw_amount_all_rows">$0.00</span>
									</div>
								</div>
							</div>

							<div class="trust-form-footer__center">
								<div class="upload_client_receipt_document trust-footer-upload">
									<input type="hidden" name="type" value="client">
									<input type="hidden" name="doctype" value="client_receipt">
									
									<div class="ledger-drag-drop-zone trust-drag-drop-zone" id="ledgerDragDropZone">
										<div class="drag-zone-inner">
											<i class="fa-solid fa-cloud-arrow-up"></i>
											<div class="drag-zone-content">
												<p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
												<small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX</small>
											</div>
										</div>
									</div>
									
									<input class="docclientreceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
									
									<div id="ledger-selected-files-display" class="ledger-selected-files-display" style="display: none;">
										<div id="ledger-files-list" class="files-list"></div>
										<button type="button" class="btn btn-sm btn-link text-danger remove-all-files" title="Remove all files">
											<i class="fa-solid fa-xmark"></i> Clear All
										</button>
									</div>
									
									<span class="file-selection-hint" style="display:none;"></span>
								</div>
							</div>

							<div class="invoice-form-footer__actions">
								<button onclick="customValidate('client_receipt_form')" type="button" class="btn btn-sm btn-primary trust-final-btn">
									<i class="fa-solid fa-check me-1"></i> Save Entry
								</button>
								<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</form>

				<!-- Tax Invoice Form -->
				<form class="form-type invoice-billing-mode-hourly" method="post" action="{{URL::to('/clients/saveinvoicereport')}}" name="invoice_receipt_form" autocomplete="off" id="invoice_receipt_form" style="display:none;">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="3">
					<input type="hidden" name="receipt_id" id="invoice_receipt_id" value="">
					<input type="hidden" name="function_type" id="invoice_function_type" value="">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_invoice" value="">

					<div class="invoice-form-shell">
						<div class="invoice-form-toolbar">
							<div class="form-group invoice-client-group">
								<label for="invoice_receipt_client">Client <span class="span_req">*</span></label>
								<div class="input-group input-group-sm invoice-client-input-wrap">
									<span class="input-group-text"><i class="fa-solid fa-user-tie"></i></span>
									<input id="invoice_receipt_client" type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="Client Name" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								</div>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
							@include('crm.clients.partials.invoice-billing-mode-toggle')
						</div>

						<div class="invoice-timesheet-scroll">
							<table class="table text_wrap table-hover table-md vertical_align invoice-lines-table">
								<thead>
									@include('crm.clients.partials.invoice-line-table-header')
								</thead>
								<tbody class="productitem_invoice">
									@include('crm.clients.partials.invoice-line-row', ['feeEarners' => $__invoiceFeeEarners])
								</tbody>
							</table>
						</div>

						<div class="invoice-form-footer">
							<div class="invoice-form-footer__left">
								<a href="javascript:;" class="btn btn-sm btn-outline-primary openproductrinfo_invoice invoice-add-line-btn">
									<i class="fa-solid fa-plus me-1"></i> Add line
								</a>
								@include('crm.clients.partials.invoice-line-totals')
							</div>
							<div class="invoice-form-footer__actions">
								<input type="hidden" name="save_type" class="save_type" value="">
								<button onclick="customValidate('invoice_receipt_form','draft')" type="button" class="btn btn-sm btn-outline-secondary invoice-draft-btn">
									<i class="fa-regular fa-bookmark me-1"></i> Save draft
								</button>
								<button onclick="customValidate('invoice_receipt_form','final')" type="button" class="btn btn-sm btn-primary invoice-final-btn">
									<i class="fa-solid fa-check me-1"></i> Create invoice
								</button>
								<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</form>

				{{-- Durable clone source (outside forms so it is never submitted/cleared) --}}
				<table id="invoice_line_row_source" class="d-none" aria-hidden="true">
					<tbody>
						@include('crm.clients.partials.invoice-line-row', ['feeEarners' => $__invoiceFeeEarners])
					</tbody>
				</table>

				<!-- Office Receipt Form -->
				<form class="form-type" method="post" action="{{URL::to('/clients/saveofficereport')}}" name="office_receipt_form" autocomplete="off" id="office_receipt_form" style="display:none;">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="2">
					<input type="hidden" name="client_matter_id" id="client_matter_id_office" value="">
					<input type="hidden" name="save_type" class="save_type_office" value="">

					<div class="invoice-form-shell office-form-shell">
						<div class="invoice-form-toolbar">
							<div class="form-group invoice-client-group">
								<label for="office_receipt_client">Client <span class="span_req">*</span></label>
								<div class="input-group input-group-sm invoice-client-input-wrap">
									<span class="input-group-text"><i class="fa-solid fa-user-tie"></i></span>
									<input id="office_receipt_client" type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="Client Name" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								</div>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>

							<!-- Quick Actions Toolbar in matching toolbar style -->
							<div class="invoice-billing-mode-bar office-quick-actions-bar">
								<div class="invoice-billing-mode-bar__main">
									<span class="invoice-billing-mode-label"><i class="fa-solid fa-bolt me-1 text-primary"></i> Quick actions</span>
									<div class="btn-group btn-group-sm invoice-billing-mode-toggle office-quick-actions-toggle" role="group">
										<button type="button" class="btn btn-outline-secondary paste-clipboard-btn" title="Paste amount from clipboard">
											<i class="fa-solid fa-clipboard me-1"></i> Paste from Clipboard
											<span class="clipboard-preview ms-1 fw-bold text-success"></span>
										</button>
										<button type="button" class="btn btn-outline-secondary repeat-last-entry-btn" title="Repeat last office receipt entry">
											<i class="fa-solid fa-arrow-rotate-right me-1"></i> Repeat Last Entry
										</button>
									</div>
								</div>
								<p class="invoice-billing-mode-hint mb-0">
									<i class="fa-solid fa-circle-info text-info me-1"></i>
									<span>Use these shortcuts to speed up data entry</span>
								</p>
							</div>
						</div>

						<div class="invoice-timesheet-scroll">
							<table class="table text_wrap table-hover table-md vertical_align invoice-lines-table">
								<thead>
									<tr class="invoice-lines-legend">
										<th class="invoice-lines-legend__cell">
											<div class="invoice-lines-legend__wrap">
												<span class="invoice-lines-legend__icon"><i class="fa-solid fa-receipt"></i></span>
												<div class="invoice-lines-legend__content">
													<span class="invoice-lines-legend__title">Line items</span>
													<span class="invoice-lines-legend__hint">Fill payment details and amounts below, then provide a clear description for the office receipt.</span>
												</div>
											</div>
										</th>
									</tr>
								</thead>
								<tbody class="productitem_office">
									<tr class="clonedrow_office invoice-line-block">
										<td class="invoice-line-block__cell">
											<div class="invoice-line-card">
												<div class="invoice-line-card__header">
													<div class="invoice-line-badge">
														<span class="invoice-line-badge__dot"></span>
														<span class="invoice-line-badge__text">Line Item</span>
													</div>
													<a class="removeitems_office invoice-line-remove" href="javascript:;" title="Remove this line item" aria-label="Remove line">
														<i class="fa-solid fa-trash-can"></i>
													</a>
												</div>

												<div class="invoice-line-card__grid office-line-grid">
													<div class="invoice-field office-col-trans-date">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Trans. date</label>
														</div>
														<div class="invoice-input-icon-wrap">
															<i class="fa-regular fa-calendar-days invoice-input-icon"></i>
															<input data-valid="required" class="form-control form-control-sm report_date_fields_office" name="trans_date[]" type="text" value="" placeholder="DD/MM/YYYY" readonly="readonly" title="Transaction date" />
														</div>
													</div>

													<div class="invoice-field office-col-entry-date">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Entry date</label>
														</div>
														<div class="invoice-input-icon-wrap">
															<i class="fa-regular fa-clock invoice-input-icon"></i>
															<input data-valid="required" class="form-control form-control-sm report_entry_date_fields_office" name="entry_date[]" type="text" value="" readonly="readonly" title="Entry date" />
														</div>
													</div>

													<div class="invoice-field office-col-invoice">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Invoice Ref. No.</label>
														</div>
														<select class="form-select form-select-sm invoice_no_cls" name="invoice_no[]">
															<option value="">Select Invoice (Optional)</option>
														</select>
													</div>

													<div class="invoice-field office-col-method">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Payment method</label>
														</div>
														<select class="form-select form-select-sm office-receipt-payment-method" name="payment_method[]" data-valid="required">
															<option value="">Select</option>
															<option value="Cash">Cash</option>
															<option value="Bank transfer">Bank transfer</option>
															<option value="EFTPOS">EFTPOS</option>
															<option value="Refund">Refund</option>
														</select>
														<div class="office-eftpos-surcharge-block" style="display:none;margin-top:6px;">
															<label class="text-muted" style="font-size:11px;margin:0;display:block;">Card surcharge ($)</label>
															<input type="text" class="form-control form-control-sm office-eftpos-surcharge-input" name="eftpos_surcharge_amount[]" inputmode="decimal" autocomplete="off" placeholder="0.00" style="font-size:12px;padding:4px 8px;" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="">
														</div>
													</div>

													<div class="invoice-field office-col-amount">
														<div class="invoice-field__header">
															<label class="invoice-field__label">Amount received</label>
														</div>
														<div class="invoice-input-prefix-wrap">
															<span class="invoice-input-prefix">$</span>
															<input data-valid="required" class="form-control form-control-sm total_deposit_amount_office" name="deposit_amount[]" type="text" inputmode="decimal" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" />
														</div>
													</div>
												</div>

												<div class="invoice-field invoice-col-desc">
													<div class="invoice-field__header">
														<label class="invoice-field__label"><i class="fa-regular fa-message text-muted me-1"></i> Description</label>
													</div>
													<textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="2" placeholder="Describe the payment or particulars for this office receipt (e.g. client consultation fee, professional legal services)..."></textarea>
												</div>
											</div>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="invoice-form-footer office-form-footer">
							<div class="invoice-form-footer__left">
								<a href="javascript:;" class="btn btn-sm btn-outline-primary openproductrinfo_office invoice-add-line-btn">
									<i class="fa-solid fa-plus me-1"></i> Add line
								</a>
								<div class="invoice-totals-card" aria-live="polite">
									<div class="invoice-totals-card__row invoice-totals-card__row--total">
										<span class="invoice-totals-card__label">Total received:</span>
										<span class="invoice-totals-card__value total_deposit_amount_all_rows_office">$0.00</span>
									</div>
								</div>
							</div>

							<div class="office-form-footer__center">
								<div class="upload_office_receipt_document office-footer-upload">
									<input type="hidden" name="type" value="client">
									<input type="hidden" name="doctype" value="office_receipt">
									
									<div class="ledger-drag-drop-zone office-drag-drop-zone" id="officeDragDropZone">
										<div class="drag-zone-inner">
											<i class="fa-solid fa-cloud-arrow-up"></i>
											<div class="drag-zone-content">
												<p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
												<small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX</small>
											</div>
										</div>
									</div>
									
									<input class="docofficereceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
									
									<div id="office-selected-files-display" class="ledger-selected-files-display" style="display: none;">
										<div id="office-files-list" class="files-list"></div>
										<button type="button" class="btn btn-sm btn-link text-danger remove-all-files-office" title="Remove all files">
											<i class="fa-solid fa-xmark"></i> Clear All
										</button>
									</div>
									
									<span class="file-selection-hint1" style="display:none;"></span>
								</div>
							</div>

							<div class="invoice-form-footer__actions">
								<button onclick="customValidate('office_receipt_form','draft')" type="button" class="btn btn-sm btn-outline-secondary invoice-draft-btn">
									<i class="fa-regular fa-bookmark me-1"></i> Save draft
								</button>
								<button onclick="customValidate('office_receipt_form','final')" type="button" class="btn btn-sm btn-primary invoice-final-btn">
									<i class="fa-solid fa-check me-1"></i> Save and finalize
								</button>
								<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</form>
		  	</div>
		</div>
	</div>
</div>

{{-- 2. Adjust Invoice Receipt Modal --}}
<!-- Create Adjust Invoice Receipt  -->
<div class="modal fade custom_modal" id="createadjustinvoicereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
		  	<div class="modal-header">
				<h5 class="modal-title">Adjust Invoice</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
		    </div>

		  	<div class="modal-body">
				<!-- Invoice Receipt Form -->
				<form class="form-type" method="post" action="{{URL::to('/clients/saveadjustinvoicereport')}}" name="adjust_invoice_receipt_form" autocomplete="off" id="adjust_invoice_receipt_form">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="3">
					<input type="hidden" name="receipt_id" id="adjust_invoice_receipt_id" value="">
					<input type="hidden" name="function_type" id="adjust_invoice_function_type" value="add">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_adjust_invoice" value="">

					<div class="row">
						<div class="col-3 col-md-3 col-lg-3">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="Invoic_no_cls" style="text-align: center;">
                                <b>Invoice No -
                                    <span class="unique_invoice_no"></span>
                                </b>
                                <input type="hidden" name="invoice_no" class="invoice_no" value="">
                            </div>
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;" title="Date shown on the tax invoice">Invoice Date</th>
                                            <th style="width:15%;color: #34395e;" title="Date this entry was posted in the system">Date Recorded</th>
                                            <th style="width:13%;color: #34395e;" title="Is GST included in the amount?">GST Included</th>
                                            <th style="width:5%;color: #34395e;" title="Type of charge being invoiced">Charge Type</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Amount</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_invoice">
                                        <tr class="clonedrow_invoice">
                                            <td>
                                                <input name="id[]" type="hidden" value="" />
                                                <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" title="Date shown on the tax invoice" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" title="Date this entry was posted in the system" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="gst_included[]">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>

                                            <td>
                                                <select class="form-control" name="payment_type[]">
                                                    <option value="">Select</option>
                                                    <option value="Adjust">Adjust/Discount</option>
                                                </select>
                                            </td>
                                            <td>
                                                <textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="3"></textarea>
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_invoice" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12 text-right">
                            <input type="hidden" name="save_type" class="save_type" value="">
                            <button onclick="customValidate('adjust_invoice_receipt_form','final')" type="button" class="btn btn-primary" style="margin:0px !important;">Create Invoice</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 3. Create Client Receipt Modal --}}
<!-- Create Client Receipt Modal -->
<div class="modal fade custom_modal" id="createclientreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Client Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/saveaccountreport')}}" name="create_client_receipt" autocomplete="off" id="create_client_receipt" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="1">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="sel_client_agent_id">Solicitor <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="agent_id" id="sel_client_agent_id">
                                    <option value="">Select solicitor</option>
                                    @foreach($__receiptModalSolicitors as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->first_name}} {{@$aplist->last_name}}@if(!empty($aplist->email)) ({{@$aplist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;">Trans. No</th>
                                            <th style="width:5%;color: #34395e;">Payment Method</th>
                                            <th style="width:35%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Deposit</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem">
                                        <tr class="clonedrow">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden" name="trans_no[]" type="hidden" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="payment_method[]">
                                                    <option value="">Select</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank tansfer">Bank tansfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control deposit_amount_per_row" name="deposit_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_deposit_amount_all_rows" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">

                            <div class="upload_client_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="client_receipt">
                                <a href="javascript:;" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Document</a>
                                <input class="docclientreceiptupload" type="file" name="document_upload[]"/>
                            </div>

                            <button onclick="customValidate('create_client_receipt')" type="button" class="btn btn-primary" style="margin:0px !important;">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

			</div>
		</div>
	</div>
</div>

{{-- 4. Create Invoice Receipt Modal --}}
<!-- Create Invoice Receipt Modal -->
<div class="modal fade custom_modal" id="createinvoicereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-xxl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Invoice</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="invoice_top_value_db" value="">
				<form class="invoice-billing-mode-hourly" method="post" action="{{URL::to('/clients/saveinvoicereport')}}" name="create_invoice_receipt" autocomplete="off" id="create_invoice_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="3">
                <input type="hidden" name="receipt_id" id="update_draft_invoice_receipt_id" value="">
                <input type="hidden" name="function_type" id="update_draft_invoice_function_type" value="">

					<div class="invoice-form-shell">
						<div class="invoice-form-toolbar">
							<div class="form-group invoice-client-group">
								<label for="create_invoice_client">Client <span class="span_req">*</span></label>
								<div class="input-group input-group-sm invoice-client-input-wrap">
									<span class="input-group-text"><i class="fa-solid fa-user-tie"></i></span>
									<input id="create_invoice_client" type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="Client Name">
								</div>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
							<div class="form-group invoice-solicitor-group">
								<label for="sel_invoice_agent_id">Solicitor <span class="span_req">*</span></label>
								<div class="input-group input-group-sm invoice-client-input-wrap">
									<span class="input-group-text"><i class="fa-solid fa-scale-balanced"></i></span>
									<select data-valid="required" class="form-control form-select crm-ts-plain" name="agent_id" id="sel_invoice_agent_id">
										<option value="">Select solicitor</option>
										@foreach($__receiptModalSolicitors as $aplist)
											<option value="{{$aplist->id}}">{{@$aplist->first_name}} {{@$aplist->last_name}}@if(!empty($aplist->email)) ({{@$aplist->email}})@endif</option>
										@endforeach
									</select>
								</div>
							</div>
							@include('crm.clients.partials.invoice-billing-mode-toggle')
						</div>

						<div class="Invoic_no_cls text-center mb-2">
							<b>Invoice No - <span class="unique_invoice_no"></span></b>
							<input type="hidden" name="invoice_no" class="invoice_no" value="">
						</div>

						<div class="invoice-timesheet-scroll">
							<table class="table text_wrap table-hover table-md vertical_align invoice-lines-table">
								<thead>
									@include('crm.clients.partials.invoice-line-table-header', ['includeTransNo' => true])
								</thead>
								<tbody class="productitem_invoice">
									@include('crm.clients.partials.invoice-line-row', ['includeTransNo' => true, 'feeEarners' => $__invoiceFeeEarners])
								</tbody>
							</table>
						</div>

						<div class="invoice-form-footer">
							<div class="invoice-form-footer__left">
								<a href="javascript:;" class="btn btn-sm btn-outline-primary openproductrinfo_invoice invoice-add-line-btn">
									<i class="fa-solid fa-plus me-1"></i> Add line
								</a>
								@include('crm.clients.partials.invoice-line-totals')
							</div>
							<div class="invoice-form-footer__actions">
								<input type="hidden" name="save_type" class="save_type" value="">
								<button onclick="customValidate('create_invoice_receipt','draft')" type="button" class="btn btn-sm btn-outline-secondary invoice-draft-btn">
									<i class="fa-regular fa-bookmark me-1"></i> Save draft
								</button>
								<button onclick="customValidate('create_invoice_receipt','final')" type="button" class="btn btn-sm btn-primary invoice-final-btn">
									<i class="fa-solid fa-check me-1"></i> Create invoice
								</button>
								<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 5. Create Office Receipt Modal --}}
<!-- Create Office Receipt Modal -->
<div class="modal fade custom_modal" id="createofficereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Office Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="office_top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/saveofficereport')}}" name="create_office_receipt" autocomplete="off" id="create_office_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="2">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="sel_office_agent_id">Solicitor <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="agent_id" id="sel_office_agent_id">
                                    <option value="">Select solicitor</option>
                                    @foreach($__receiptModalSolicitors as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->first_name}} {{@$aplist->last_name}}@if(!empty($aplist->email)) ({{@$aplist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;">Receipt No</th>
                                            <th style="width:15%;color: #34395e;" title="Invoice number this receipt is linked to (if any)">Invoice Ref. No.</th>
                                            <th style="width:5%;color: #34395e;">Payment method</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;" title="Amount received into office account">Amount Received</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_office">
                                        <tr class="clonedrow_office">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields_office" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_office" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no_office" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden_office" name="trans_no[]" type="hidden" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control invoice_no_cls"  name="invoice_no[]">
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control" name="payment_method[]" data-valid="required" >
                                                    <option value="">Select</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank tansfer">Bank tansfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control total_withdrawal_amount_office" name="withdraw_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_office" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_office" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_office"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                            <div class="upload_office_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="office_receipt">
                                
                                <!-- NEW: Drag and Drop Zone -->
                                <div class="ledger-drag-drop-zone office-drag-drop-zone" id="officeDragDropZone2">
                                    <div class="drag-zone-inner">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <div class="drag-zone-content">
                                            <p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
                                            <small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX (Multiple files allowed)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Keep existing file input (hidden, used as fallback) -->
                                <input class="docofficereceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
                                
                                <!-- File selection display (shown after files are selected) -->
                                <div id="office-selected-files-display2" class="ledger-selected-files-display" style="display: none;">
                                    <div id="office-files-list2" class="files-list"></div>
                                    <button type="button" class="btn btn-sm btn-link text-danger remove-all-files-office" title="Remove all files">
                                        <i class="fa-solid fa-xmark"></i> Clear All
                                    </button>
                                </div>
                            </div>

                            <button onclick="customValidate('create_office_receipt')" type="button" class="btn btn-primary" style="margin: 0px !important;">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

			</div>
		</div>
	</div>
</div>

{{-- 6. Create Journal Modal --}}
<!-- Create Journal Modal -->
<div class="modal fade custom_modal" id="createjournalreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Journal</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="journal_top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/savejournalreport')}}" name="create_journal_receipt" autocomplete="off" id="create_journal_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="4">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="sel_journal_agent_id">Solicitor <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control crm-ts-plain" name="agent_id" id="sel_journal_agent_id">
                                    <option value="">Select solicitor</option>
                                    @foreach($__receiptModalSolicitors as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->first_name}} {{@$aplist->last_name}}@if(!empty($aplist->email)) ({{@$aplist->email}})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:12%;color: #34395e;">Trans. No</th>
                                            <th style="width:13%;color: #34395e;">Invoice No</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:15%;color: #34395e;">Transfer</th>
											<th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_journal">
                                        <tr class="clonedrow_journal">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields_journal" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_journal" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no_journal" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden_journal" name="trans_no[]" type="hidden" value="" />
                                            </td>

                                            <td>
                                                <select data-valid="required" class="form-control invoice_no_cls"  name="invoice_no[]">
                                                </select>
                                            </td>

                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control total_withdrawal_amount_journal" name="withdraw_amount[]" type="text" value="" />
                                            </td>

					                        <td>
                                                <a class="removeitems_journal" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:48.99%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2" style="width:10.99%;">
                                                <span class="total_withdraw_amount_all_rows_journal" style="color: #34395e;"></span>
                                            </td>
										</tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_journal"><i class="fa-solid fa-plus"></i> Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">

                            <div class="upload_journal_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="journal_receipt">
                                <a href="javascript:;" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Document</a>

                                <input class="docjournalreceiptupload" type="file" name="document_upload[]"/>
                            </div>

                            <button onclick="customValidate('create_journal_receipt')" type="button" class="btn btn-primary" style="margin:0px !important;">Save Entry</button>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					</div>
                    </div>
			</form>
            </div>
		</div>
	</div>
</div>

<script>
(function () {
    'use strict';

    /**
     * Agent dropdowns inside receipt / invoice / office / journal modals.
     * Static <select> options rendered server-side; use plain single Tom Select
     * with dropdownParent set to the modal element so the dropdown renders correctly.
     */
    var agentModalMap = [
        { modal: '#createclientreceiptmodal',  select: '#sel_client_agent_id'  },
        { modal: '#createinvoicereceiptmodal', select: '#sel_invoice_agent_id' },
        { modal: '#createofficereceiptmodal',  select: '#sel_office_agent_id'  },
        { modal: '#createjournalreceiptmodal', select: '#sel_journal_agent_id' }
    ];

    agentModalMap.forEach(function (entry) {
        jQuery(document).on('shown.bs.modal', entry.modal, function () {
            var modalEl = this;
            var sel = modalEl.querySelector(entry.select);
            if (!sel) return;
            if (typeof destroyTS === 'function') destroyTS(sel);
            if (typeof initTS === 'function' && typeof buildPlainSingleTomSelectConfig === 'function') {
                initTS(sel, buildPlainSingleTomSelectConfig({ dropdownParent: modalEl }));
            }
        });

        jQuery(document).on('hidden.bs.modal', entry.modal, function () {
            var sel = this.querySelector(entry.select);
            if (sel && typeof destroyTS === 'function') destroyTS(sel);
        });
    });
}());
</script>

