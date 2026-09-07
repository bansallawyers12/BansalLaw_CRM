<!-- Activity Feed (Timeline tab; single #activity-feed instance) -->
<aside class="activity-feed activity-feed--simple" id="activity-feed">
    @php
        $_billingViewer = Auth::guard('admin')->user() ?? Auth::user();
        $_canTimelineBilling = $_billingViewer instanceof \App\Models\Staff
            && $_billingViewer->canUseTimelineBilling();
        $_isTimelineBillingSuperAdmin = $_billingViewer instanceof \App\Models\Staff
            && $_billingViewer->hasEffectiveSuperAdminPrivileges();
    @endphp

    @if(!empty($_canTimelineBilling))
    <div class="modal fade activity-feed-billing-modal"
         id="activity-feed-billing-panel"
         tabindex="-1"
         role="dialog"
         aria-labelledby="activity-feed-billing-title"
         aria-hidden="true"
         data-backdrop="static"
         data-keyboard="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable activity-feed-billing-modal__dialog" role="document">
            <div class="modal-content activity-feed-billing-modal__content">
                <div class="modal-header activity-feed-billing-panel__header">
                    <div class="activity-feed-billing-panel__title-block">
                        <h4 class="modal-title activity-feed-billing-panel__title" id="activity-feed-billing-title">Billing statement</h4>
                        <p class="activity-feed-billing-panel__meta">
                            <span class="activity-feed-billing-count" data-billing-selection-meta>0 lines selected</span>
                            @if(!empty($_isTimelineBillingSuperAdmin))
                            <span class="activity-feed-billing-sa-badge">Super Admin</span>
                            @else
                            <span class="activity-feed-billing-sa-badge">Billing access</span>
                            @endif
                        </p>
                    </div>
                    <div class="activity-feed-billing-panel__actions">
                        <button type="button" class="btn btn-sm btn-primary" id="activity-feed-billing-show-selected" title="Show selected lines in statement format">
                            <i class="fa-solid fa-list" aria-hidden="true"></i> Show selected lines
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="activity-feed-billing-reload" title="Reload billable items from the current timeline view">
                            <i class="fa-solid fa-rotate" aria-hidden="true"></i> Reload
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="activity-feed-billing-add-disb">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> Disbursement
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="activity-feed-billing-clear" title="Clear fee lines and disbursements">Clear</button>
                        <button type="button" class="btn btn-sm btn-link activity-feed-billing-close" id="activity-feed-billing-close" title="Close billing">Done</button>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="modal-body activity-feed-billing-panel__body">
                    <div class="activity-feed-billing-section">
                        <div class="activity-feed-billing-section__head">
                            <h5 class="activity-feed-billing-section__title">Billing structure</h5>
                            <div class="activity-feed-billing-structure-actions">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="activity-feed-billing-print-structure" title="Print complete billing structure">
                                    <i class="fa-solid fa-print" aria-hidden="true"></i> Print
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="activity-feed-billing-share-structure" title="Share complete billing structure">
                                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Share
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="activity-feed-billing-email-structure" title="Email complete billing invoice">
                                    <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" id="activity-feed-billing-save-rates">Save amounts</button>
                            </div>
                        </div>
                        <p class="activity-feed-billing-structure-help">Set how much (incl. GST) is charged for each activity type. Only Super Admin can change these amounts. Timeline lines use this schedule automatically.</p>
                        <div class="activity-feed-billing-table-wrap activity-feed-billing-table-wrap--structure">
                            <table class="table table-sm activity-feed-billing-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col" class="activity-feed-billing-col-sno">#</th>
                                        <th scope="col">Category</th>
                                        <th scope="col">Where it applies</th>
                                        <th scope="col" class="text-right">Amount (incl. GST)</th>
                                    </tr>
                                </thead>
                                <tbody id="activity-feed-billing-structure-body">
                                    <tr class="activity-feed-billing-empty">
                                        <td colspan="4">Loading billing structure…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="activity-feed-billing-save-status" id="activity-feed-billing-save-status" hidden></p>
                    </div>

                    <div class="activity-feed-billing-section">
                        <div class="activity-feed-billing-section__head">
                            <h5 class="activity-feed-billing-section__title">Professional fees</h5>
                            <label class="activity-feed-billing-show-all">
                                <input type="checkbox" id="activity-feed-billing-show-all">
                                Show non-billable
                            </label>
                        </div>
                        <div class="activity-feed-billing-table-wrap">
                            <table class="table table-sm activity-feed-billing-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col" class="activity-feed-billing-col-include">
                                            <input type="checkbox" id="activity-feed-billing-select-all" title="Select all" aria-label="Select all fee lines">
                                        </th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Description</th>
                                        <th scope="col">Category</th>
                                        <th scope="col" class="text-right">Qty</th>
                                        <th scope="col" class="text-right">Rate</th>
                                        <th scope="col" class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="activity-feed-billing-fees-body">
                                    <tr class="activity-feed-billing-empty">
                                        <td colspan="7">Open Billing to load timeline items, or click Reload.</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="text-right">Fees subtotal (incl. GST)</td>
                                        <td class="text-right"><strong data-billing-field="fees_incl">$0.00</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="activity-feed-billing-section">
                        <div class="activity-feed-billing-section__head">
                            <h5 class="activity-feed-billing-section__title">Disbursements</h5>
                        </div>
                        <div class="activity-feed-billing-table-wrap activity-feed-billing-table-wrap--disb">
                            <table class="table table-sm activity-feed-billing-table mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Description</th>
                                        <th scope="col" class="text-right">Net</th>
                                        <th scope="col" class="text-right">GST</th>
                                        <th scope="col" class="text-right">Incl. GST</th>
                                        <th scope="col" class="activity-feed-billing-col-remove"></th>
                                    </tr>
                                </thead>
                                <tbody id="activity-feed-billing-disb-body">
                                    <tr class="activity-feed-billing-empty" data-disb-empty="1">
                                        <td colspan="5">No disbursements — use Disbursement to add one (e.g. InfoTrack).</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="text-right">Disb. subtotal</td>
                                        <td class="text-right"><strong data-billing-field="disb_net">$0.00</strong></td>
                                        <td class="text-right"><strong data-billing-field="disb_gst">$0.00</strong></td>
                                        <td class="text-right"><strong data-billing-field="disb_incl">$0.00</strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <footer class="activity-feed-billing-panel__footer">
                        <div class="activity-feed-billing-panel__footer-row">
                            <span>Total fees &amp; disbursements (net)</span>
                            <strong data-billing-field="total_net">$0.00</strong>
                        </div>
                        <div class="activity-feed-billing-panel__footer-row">
                            <span>GST included</span>
                            <strong data-billing-field="gst_included">$0.00</strong>
                        </div>
                        <div class="activity-feed-billing-panel__footer-row activity-feed-billing-panel__footer-row--due">
                            <span>Total amount due</span>
                            <strong data-billing-field="total_due">$0.00</strong>
                        </div>
                    </footer>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="activity-feed-billing-statement-modal" tabindex="-1" role="dialog" aria-labelledby="activity-feed-billing-statement-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content activity-feed-billing-statement-modal__content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activity-feed-billing-statement-title">Billing statement — selected lines</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="activity-feed-billing-statement__inner">
                        <section class="billing-statement-block">
                            <h5 class="billing-statement-block__title">Professional Fees</h5>
                            <table class="billing-statement-table">
                                <thead>
                                    <tr>
                                        <th scope="col" class="billing-statement-col-date">Date</th>
                                        <th scope="col">Description</th>
                                        <th scope="col" class="text-right">Amount (Including GST)</th>
                                    </tr>
                                </thead>
                                <tbody id="activity-feed-billing-statement-fees"></tbody>
                                <tfoot>
                                    <tr class="billing-statement-subtotal">
                                        <td colspan="2"></td>
                                        <td class="text-right"><strong data-statement-field="fees_incl">$0.00</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </section>

                        <section class="billing-statement-block">
                            <h5 class="billing-statement-block__title">Disbursements</h5>
                            <table class="billing-statement-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Description</th>
                                        <th scope="col" class="text-right">Amount</th>
                                        <th scope="col" class="text-right">GST</th>
                                        <th scope="col" class="text-right">Amount (Including GST)</th>
                                    </tr>
                                </thead>
                                <tbody id="activity-feed-billing-statement-disb"></tbody>
                                <tfoot>
                                    <tr class="billing-statement-subtotal">
                                        <td></td>
                                        <td class="text-right"><strong data-statement-field="disb_net">$0.00</strong></td>
                                        <td class="text-right"><strong data-statement-field="disb_gst">$0.00</strong></td>
                                        <td class="text-right"><strong data-statement-field="disb_incl">$0.00</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </section>

                        <aside class="billing-statement-totals" aria-label="Statement totals">
                            <div class="billing-statement-totals__row">
                                <span>Total Fees and Disbursements</span>
                                <strong data-statement-field="total_net">$0.00</strong>
                            </div>
                            <div class="billing-statement-totals__row">
                                <span>GST Included</span>
                                <strong data-statement-field="gst_included">$0.00</strong>
                            </div>
                            <div class="billing-statement-totals__row billing-statement-totals__row--due">
                                <span>Total Amount Due</span>
                                <strong data-statement-field="total_due">$0.00</strong>
                            </div>
                        </aside>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif

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
            @if(!empty($_canTimelineBilling))
            <div class="activity-feed-billing-summary" id="activity-feed-billing-summary" hidden aria-live="polite">
                <div class="activity-feed-billing-summary__cell">
                    <span class="activity-feed-billing-summary__label">Net</span>
                    <span class="activity-feed-billing-summary__value" data-billing-field="total_net">$0.00</span>
                </div>
                <div class="activity-feed-billing-summary__cell">
                    <span class="activity-feed-billing-summary__label">GST</span>
                    <span class="activity-feed-billing-summary__value" data-billing-field="gst_included">$0.00</span>
                </div>
                <div class="activity-feed-billing-summary__cell activity-feed-billing-summary__cell--due">
                    <span class="activity-feed-billing-summary__label">Due</span>
                    <span class="activity-feed-billing-summary__value" data-billing-field="total_due">$0.00</span>
                </div>
            </div>
            <button type="button"
                    class="btn btn-sm btn-link p-0 activity-feed-billing-calc"
                    id="activity-feed-billing-calc"
                    title="Calculate billing from timeline"
                    aria-expanded="false"
                    aria-controls="activity-feed-billing-panel">
                <i class="fa-solid fa-calculator" aria-hidden="true"></i>
                <span class="activity-feed-billing-calc__label" data-billing-calc-label>Billing</span>
            </button>
            @endif
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
        <li class="feed-item feed-item--loading" role="status" aria-live="polite">
            <span class="activity-feed-loader" aria-hidden="true">
                <i class="fa-solid fa-spinner fa-spin"></i>
            </span>
            <p class="mb-0 small activity-feed-loader__text">Loading…</p>
        </li>
    </ul>
</aside>
