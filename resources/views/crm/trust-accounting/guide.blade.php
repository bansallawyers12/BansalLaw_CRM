@extends('layouts.crm_client_detail')
@section('title', 'Trust accounting — user guide')

@section('content')
<style>
.guide-section        { margin-bottom: 2.5rem; }
.guide-step           { display: flex; gap: 14px; margin-bottom: 1.1rem; }
.guide-step-num       { flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%;
                        background: #0d6efd; color: #fff; font-weight: 700; font-size: 13px;
                        display: flex; align-items: center; justify-content: center; margin-top: 1px; }
.guide-step-body      { flex: 1; }
.guide-warning        { border-left: 4px solid #ffc107; background: #fffbf0; padding: 10px 14px;
                        border-radius: 4px; font-size: 13.5px; }
.guide-tip            { border-left: 4px solid #198754; background: #f0fff4; padding: 10px 14px;
                        border-radius: 4px; font-size: 13.5px; }
.guide-law            { border-left: 4px solid #6f42c1; background: #f8f5ff; padding: 10px 14px;
                        border-radius: 4px; font-size: 13.5px; }
.guide-card           { border: 1px solid #dee2e6; border-radius: 6px; padding: 1.2rem 1.4rem; margin-bottom: 1.2rem; }
.guide-card h5        { margin-bottom: .6rem; font-size: 1rem; }
.guide-flow           { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: .5rem 0 .8rem; }
.guide-flow-item      { background: #e9ecef; border-radius: 20px; padding: 3px 12px; font-size: 12.5px; }
.guide-flow-arrow     { color: #6c757d; font-size: 13px; }
.toc a                { color: #0d6efd; text-decoration: none; }
.toc a:hover          { text-decoration: underline; }
.toc li               { margin-bottom: 4px; }
.section-anchor       { scroll-margin-top: 72px; }
</style>

<div class="main-content">
    <section class="section">
        <div class="section-body">

            {{-- ── Header ── --}}
            <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-2">
                <div>
                    <h4 class="mb-1"><i class="fas fa-book text-secondary me-2"></i>Trust accounting — user guide</h4>
                    <p class="text-muted mb-0" style="font-size:14px;">
                        Step-by-step instructions for every part of the VLSB+C-compliant trust module built into this CRM.
                        Super-admin access required for all trust admin screens.
                    </p>
                </div>
                <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Reports hub
                </a>
            </div>

            {{-- ── Law callout ── --}}
            <div class="guide-law mb-4">
                <strong><i class="fas fa-gavel me-1"></i> Relevant law (Victoria)</strong> — This module implements
                the <em>Legal Profession Uniform Law Application Act 2014 (Vic)</em> and the
                <em>Legal Profession Uniform General Rules 2015</em>, specifically:
                Rule&nbsp;42 (withdrawal authority for fee transfers),
                Rule&nbsp;47 (trust receipts / payments records),
                Rule&nbsp;48 (monthly bank reconciliation).
                The system does not replace qualified legal accounting advice.
            </div>

            {{-- ── Table of contents ── --}}
            <div class="card mb-5">
                <div class="card-header fw-semibold">Contents</div>
                <div class="card-body">
                    <ol class="toc mb-0" style="column-count:2; column-gap:2rem;">
                        <li><a href="#section-overview">System overview</a></li>
                        <li><a href="#section-setup">Initial setup</a></li>
                        <li><a href="#section-daily">Day-to-day: recording trust entries</a></li>
                        <li><a href="#section-rule42">Rule 42 — fee transfer authority</a></li>
                        <li><a href="#section-reports">Reports hub</a></li>
                        <li><a href="#section-trial-balance">Trial balance</a></li>
                        <li><a href="#section-journals">Receipts &amp; payments journals</a></li>
                        <li><a href="#section-reconciliation">Bank reconciliation (Rule 48)</a></li>
                        <li><a href="#section-periods">Period locks</a></li>
                        <li><a href="#section-audit">Audit log</a></li>
                        <li><a href="#section-sequences">Practice sequences</a></li>
                        <li><a href="#section-monthend">Month-end checklist</a></li>
                    </ol>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 1. OVERVIEW --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-overview" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">1. System overview</h5>

                <p>The trust module is a single, integrated workflow. Here is how the pieces fit together:</p>

                <div class="guide-flow">
                    <span class="guide-flow-item"><i class="fas fa-university me-1"></i> Client trust entry</span>
                    <span class="guide-flow-arrow">→</span>
                    <span class="guide-flow-item">Trust ledger (per client/matter)</span>
                    <span class="guide-flow-arrow">→</span>
                    <span class="guide-flow-item">Receipts / Payments journal</span>
                    <span class="guide-flow-arrow">→</span>
                    <span class="guide-flow-item">Trial balance</span>
                    <span class="guide-flow-arrow">→</span>
                    <span class="guide-flow-item">Bank reconciliation</span>
                    <span class="guide-flow-arrow">→</span>
                    <span class="guide-flow-item">Period lock</span>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-users me-2 text-primary"></i>Who can access trust admin?</h5>
                            <p class="mb-0 small">Only staff with <strong>super-admin effective privileges</strong>
                            (the checkbox in the Staff Console) can access the trust admin screens
                            (reports, reconciliation, period locks, etc.). Regular staff can post
                            client ledger entries from the client detail page but cannot lock
                            periods or export reports.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-receipt me-2 text-success"></i>Transaction numbering</h5>
                            <p class="mb-0 small">Every trust entry gets a unique <strong>TR-{year}-NNNNNN</strong>
                            receipt number automatically. Fee transfers issued as journal
                            entries get <strong>TJ-{year}-NNNNNN</strong>. The year is the
                            Victorian trust year start (1 April). Gaps in TR-* numbers must be
                            explainable during an external examination.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 2. SETUP --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-setup" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">2. Initial setup <small class="text-muted fw-normal">(do once before going live)</small></h5>

                <div class="guide-step">
                    <div class="guide-step-num">A</div>
                    <div class="guide-step-body">
                        <strong>Add a trust bank account</strong> —
                        Go to <a href="{{ route('trust-accounting.bank-accounts.index') }}">Trust accounting → Bank accounts</a>.
                        Click <em>Add account</em>. Enter the BSB, account number, account name, and
                        the institution name exactly as shown on your ADI trust account statements.
                        You need at least one active account before bank reconciliation works.
                    </div>
                </div>

                <div class="guide-step">
                    <div class="guide-step-num">B</div>
                    <div class="guide-step-body">
                        <strong>Configure Rule 42 authority types</strong> —
                        Go to <a href="{{ route('trust-accounting.withdrawal-authority-types.index') }}">Trust accounting → Rule 42 types</a>.
                        Add a label for each authority basis your practice uses, for example:
                        <ul class="mt-1 mb-0 small">
                            <li><em>Written direction from client (s.142 LPUL)</em></li>
                            <li><em>Written costs agreement signed</em></li>
                            <li><em>Judgment or order of court</em></li>
                            <li><em>Supervisor override — Rule 42(5)</em></li>
                        </ul>
                        <div class="guide-tip mt-2">
                            <strong>Tip:</strong> Keep labels short but precise. They appear on the
                            client ledger, the payments journal, and in CSV exports reviewed by examiners.
                            You can deactivate a type later without losing historical records.
                        </div>
                    </div>
                </div>

                <div class="guide-step">
                    <div class="guide-step-num">C</div>
                    <div class="guide-step-body">
                        <strong>Assign trust Rule 42 supervisor (optional)</strong> —
                        In the Staff Console (Admin → Staff → Edit), check
                        <em>"Can act as Rule 42 trust supervisor"</em> on the relevant partner
                        or principal. Their name appears in the supervisor-override tooltip on
                        the payments journal for fee transfers where an override was recorded.
                    </div>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 3. DAILY ENTRIES --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-daily" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">3. Day-to-day — recording trust entries</h5>

                <p class="small text-muted">
                    All client-level entries are made from <strong>Client → Accounts tab</strong>,
                    <em>not</em> from the admin trust screens. The admin screens are reporting
                    and control tools only.
                </p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-arrow-circle-down me-2 text-success"></i>Trust Deposit (money received)</h5>
                            <ol class="small mb-0">
                                <li>Open the client record and click the <strong>Accounts</strong> tab.</li>
                                <li>Click <strong>"Trust Account Entry"</strong> (green button).</li>
                                <li>Select type <em>Deposit</em>.</li>
                                <li>Enter the transaction date, amount, payment method, and a
                                    description (e.g. "Initial retainer — <em>Matter ref</em>").</li>
                                <li>If the deposit relates to an existing invoice, select the invoice
                                    in the <em>Invoice</em> field (this allocates the trust receipt).</li>
                                <li>Click Save. A <strong>TR-</strong> reference is issued automatically.</li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-exchange-alt me-2 text-warning"></i>Fee Transfer (trust → office)</h5>
                            <ol class="small mb-0">
                                <li>Click <strong>"Trust Account Entry"</strong> and select type
                                    <em>Fee Transfer</em>.</li>
                                <li>Enter date, amount and the invoice number being satisfied.</li>
                                <li>The Rule 42 authority block appears — select the authority type
                                    and fill in the notice date. See <a href="#section-rule42">Section 4</a>
                                    for details.</li>
                                <li>Click Save. The ledger balance reduces immediately.</li>
                            </ol>
                            <div class="guide-warning mt-2">
                                <strong>Important:</strong> A Fee Transfer <em>without</em> a Rule 42
                                authority record will show "—" in the Rule 42 column of the payments
                                journal. Examiners will flag this. Always complete the authority block.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-arrow-circle-up me-2 text-danger"></i>Disbursement (payment from trust)</h5>
                            <ol class="small mb-0">
                                <li>Select type <em>Disbursement</em>.</li>
                                <li>Describe exactly what the payment is for
                                    (e.g. "Barrister fee — J. Smith — Brief ref 123").</li>
                                <li>Rule 42 authority is not required for disbursements, but record
                                    the payment method and any supporting reference.</li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-undo-alt me-2" style="color:#6f42c1;"></i>Refund to client</h5>
                            <ol class="small mb-0">
                                <li>Select type <em>Refund</em>.</li>
                                <li>Enter the date and amount. The running balance will decrease.</li>
                                <li>Ensure the refund is backed by a bank payment before posting.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="guide-tip mt-1">
                    <strong>Viewing the ledger:</strong> The Trust Account Ledger section on the
                    Accounts tab shows a running balance, transaction type icons, and receipt
                    numbers. Voided rows appear with strikethrough. A green tick icon next to
                    the date means the receipt has been verified.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 4. RULE 42 --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-rule42" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">4. Rule 42 — fee transfer authority</h5>

                <div class="guide-law mb-3">
                    <strong>What is Rule 42?</strong> — Rule 42 of the <em>Legal Profession Uniform General Rules 2015</em>
                    requires a law practice to have a <strong>written authority</strong> before withdrawing trust money
                    to pay legal costs. The authority must be given by the client (or another person on whose behalf
                    the money is held) and must specify the amount or the basis for calculating it.
                </div>

                <div class="guide-step">
                    <div class="guide-step-num">1</div>
                    <div class="guide-step-body">
                        <strong>Before posting a Fee Transfer</strong>, confirm you hold a signed costs agreement,
                        a written direction, or a court order. Choose the matching authority type from the
                        dropdown (these are the types you configured in setup step B).
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">2</div>
                    <div class="guide-step-body">
                        <strong>Notice given date</strong> — Enter the date the client was notified of the
                        withdrawal (e.g. the date the tax invoice was sent).
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">3</div>
                    <div class="guide-step-body">
                        <strong>Authority notes</strong> — Optionally record the document reference,
                        e.g. "Tax invoice INV-2026-0042 emailed to client 12 May 2026".
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">4</div>
                    <div class="guide-step-body">
                        <strong>Supervisor override (Rule 42(5))</strong> — In exceptional circumstances,
                        a supervising principal may authorise a withdrawal without the usual written direction.
                        Check this box only when a supervisor has specifically approved the withdrawal, and
                        record the reason. This is logged in the audit trail and flagged in the payments journal.
                    </div>
                </div>

                <div class="guide-card mt-2">
                    <h5><i class="fas fa-eye me-2 text-secondary"></i>Where Rule 42 data appears</h5>
                    <ul class="small mb-0">
                        <li><strong>Client Accounts tab → Trust ledger</strong> — Rule 42 column shows the
                            authority type label (hover for full details). Non-fee-transfer rows show "—".</li>
                        <li><strong>Payments journal</strong> — Five extra columns: authority type, notice date,
                            notes, supervisor override, override reason.</li>
                        <li><strong>Payments journal CSV</strong> — Same five columns; UTF-8 BOM so Excel
                            opens it correctly without encoding issues.</li>
                        <li><strong>Audit log</strong> — Every Rule 42 authority record creation is logged.</li>
                    </ul>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 5. REPORTS HUB --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-reports" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">5. Reports hub</h5>

                <p class="small">
                    Access at <a href="{{ route('trust-accounting.reports.index') }}">Trust accounting → Reports</a>.
                    This is the starting point for all external examination supporting schedules.
                </p>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="guide-card h-100">
                            <h5><i class="fas fa-balance-scale me-2 text-primary"></i>Trial balance</h5>
                            <p class="small mb-0">Funds held per client/matter as at a date. Use this to
                            confirm total trust holdings match the bank balance.</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="guide-card h-100">
                            <h5><i class="fas fa-arrow-down me-2 text-success"></i>Receipts journal</h5>
                            <p class="small mb-0">All money-in movements in a date range.
                            Includes payer name, banking date, and invoice reference.</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="guide-card h-100">
                            <h5><i class="fas fa-arrow-up me-2 text-danger"></i>Payments journal</h5>
                            <p class="small mb-0">All money-out movements in a date range.
                            Includes full Rule 42 authority columns for fee transfers.</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="guide-card h-100">
                            <h5><i class="fas fa-link me-2 text-info"></i>Bank reconciliation</h5>
                            <p class="small mb-0">Match bank statement lines to ledger rows. Shows
                            movement variance and trial balance total for Rule 48 sign-off.</p>
                        </div>
                    </div>
                </div>

                <div class="guide-tip">
                    <strong>CSV exports</strong> — Every report has a <em>CSV</em> button. The file has a
                    UTF-8 BOM so it opens correctly in Excel without "garbled characters". Open Excel →
                    double-click the downloaded <code>.csv</code> file directly (do not use
                    <em>Data → From Text</em> for these files).
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 6. TRIAL BALANCE --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-trial-balance" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">6. Trial balance</h5>

                <div class="guide-step">
                    <div class="guide-step-num">1</div>
                    <div class="guide-step-body">
                        Go to <a href="{{ route('trust-accounting.reports.trial-balance') }}">Reports → Trial balance</a>.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">2</div>
                    <div class="guide-step-body">
                        Set <strong>As at</strong> to the last day of the month you are reconciling,
                        e.g. <code>2026-05-31</code>. Click <strong>Run</strong>.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">3</div>
                    <div class="guide-step-body">
                        The <strong>Total</strong> row at the bottom is the practice total of all
                        client trust funds. This figure must equal your bank statement closing balance
                        (after outstanding items are cleared). Any difference is a reconciling item.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">4</div>
                    <div class="guide-step-body">
                        Click <strong>CSV</strong> to download for the examination file.
                        The columns are: client reference, matter reference, client name, balance.
                    </div>
                </div>
                <div class="guide-tip">
                    <strong>Include zero balances</strong> — Tick this if you want to see matters
                    where all trust money has been disbursed. Useful for confirming closed matters
                    have a nil balance before archiving.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 7. JOURNALS --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-journals" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">7. Receipts &amp; Payments journals</h5>

                <p class="small text-muted">Both journals work the same way — select a date range, optionally filter by client, then view or export.</p>

                <div class="guide-step">
                    <div class="guide-step-num">1</div>
                    <div class="guide-step-body">
                        Go to <a href="{{ route('trust-accounting.reports.receipts-journal') }}">Receipts journal</a>
                        or <a href="{{ route('trust-accounting.reports.payments-journal') }}">Payments journal</a>.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">2</div>
                    <div class="guide-step-body">
                        Set the <strong>From</strong> and <strong>To</strong> dates to the
                        month you are reviewing. If you only supply one date, the other is
                        set automatically to the start/end of that calendar month.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">3</div>
                    <div class="guide-step-body">
                        Click <strong>Run</strong> to view on screen (100 rows per page), or
                        <strong>CSV</strong> to download the full set for the date range.
                    </div>
                </div>

                <div class="guide-card mt-2">
                    <h5 class="small fw-semibold">What the columns mean (Payments journal)</h5>
                    <table class="table table-sm table-bordered mb-0 small">
                        <thead class="table-light">
                            <tr><th>Column</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Trans date</td><td>Date of the ledger transaction (d/m/Y format as stored)</td></tr>
                            <tr><td>No.</td><td>Unique TR- or TJ- reference number</td></tr>
                            <tr><td>Type</td><td>Fee Transfer / Disbursement / Refund</td></tr>
                            <tr><td>Client / Matter</td><td>Client reference and matter number</td></tr>
                            <tr><td>Amount</td><td>Amount withdrawn from trust</td></tr>
                            <tr><td>Method</td><td>Payment method recorded at posting</td></tr>
                            <tr><td>Invoice</td><td>Invoice number where a fee transfer satisfies a specific invoice</td></tr>
                            <tr><td>Rule 42 type</td><td>Authority type label (configured in setup)</td></tr>
                            <tr><td>Notice</td><td>Date client was notified of the withdrawal</td></tr>
                            <tr><td>Rule 42 notes</td><td>Free-text reference for the authority document</td></tr>
                            <tr><td>Override</td><td>Yes / blank — whether a supervisor override was recorded</td></tr>
                            <tr><td>Override reason</td><td>The stated reason if an override was used</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 8. BANK RECONCILIATION --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-reconciliation" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">8. Bank reconciliation <small class="text-muted fw-normal">(Rule 48 — monthly)</small></h5>

                <div class="guide-law mb-3">
                    <strong>Rule 48</strong> requires a law practice to reconcile its trust bank account
                    with its trust ledger records <strong>at least once a month</strong>. The reconciliation
                    statement must be signed by the principal and kept for at least 7 years.
                </div>

                <div class="guide-step">
                    <div class="guide-step-num">1</div>
                    <div class="guide-step-body">
                        Go to <a href="{{ route('trust-accounting.reconciliation.index') }}">Reports → Bank reconciliation</a>.
                        Select the trust bank account and set the date range to the month you are reconciling.
                        Click <strong>Run</strong>.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">2</div>
                    <div class="guide-step-body">
                        <strong>Add each line from your bank statement</strong> using the
                        <em>Add bank line</em> form:
                        <ul class="mt-1 mb-0 small">
                            <li><strong>Value date</strong> — the date shown on the statement for that line.</li>
                            <li><strong>Amount</strong> — positive for credits (money in); negative for debits (money out).</li>
                            <li><strong>Narrative / Reference</strong> — copy from the statement for traceability.</li>
                        </ul>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">3</div>
                    <div class="guide-step-body">
                        <strong>Match each statement line to a ledger row</strong>: For each
                        unmatched statement line, the system shows ledger rows with the exact
                        same amount. Select the correct one and click <strong>Match</strong>.
                        Matched lines show a green "Ledger #" badge.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">4</div>
                    <div class="guide-step-body">
                        <strong>Check the variance card</strong>: When all lines are matched,
                        the <em>Movement variance</em> card should show <strong>$0.00</strong>
                        (green border). A non-zero variance means either a ledger entry is
                        missing, a bank line has not been entered, or there is a data-entry error.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">5</div>
                    <div class="guide-step-body">
                        <strong>Confirm the trial balance total</strong> matches your bank closing
                        balance. This figure is shown in the blue information bar at the top of
                        the reconciliation page. Print or screenshot the screen for your signed
                        Rule 48 reconciliation statement.
                    </div>
                </div>

                <div class="guide-warning mt-2">
                    The system helps you match lines but does <strong>not</strong> automatically
                    produce a signed reconciliation statement. You must print the screen (or export
                    the trial balance + journals to CSV), note any outstanding items, have the
                    principal sign it, and retain it for 7 years.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 9. PERIOD LOCKS --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-periods" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">9. Period locks</h5>

                <p class="small">
                    After completing the monthly reconciliation, lock the period to prevent staff
                    from accidentally altering historical entries.
                </p>

                <div class="guide-step">
                    <div class="guide-step-num">1</div>
                    <div class="guide-step-body">
                        Go to <a href="{{ route('trust-accounting.periods.index') }}">Trust accounting → Period locks</a>.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">2</div>
                    <div class="guide-step-body">
                        Enter the <strong>Period start</strong> and <strong>Period end</strong>
                        (e.g. 2026-05-01 to 2026-05-31), add an optional note
                        (e.g. "May 2026 bank reconciliation completed — signed by J. Bansal"),
                        and click <strong>Lock period</strong>.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">3</div>
                    <div class="guide-step-body">
                        Once locked, <strong>staff cannot</strong> create new trust entries,
                        void existing entries, or edit trust metadata for any transaction
                        dated within that range. The lock is logged in the audit trail.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">4</div>
                    <div class="guide-step-body">
                        To <strong>unlock</strong> a period (e.g. to correct a data error),
                        click <em>Unlock</em> next to the period. You will be asked for a reason.
                        All unlock actions are also audit-logged. Re-lock after corrections.
                    </div>
                </div>

                <div class="guide-warning mt-2">
                    Overlapping locked periods are not allowed. If you try to lock a range that
                    overlaps an existing locked period the system will reject it and tell you
                    which existing period is in conflict.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 10. AUDIT LOG --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-audit" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">10. Audit log</h5>

                <p class="small">
                    The audit log is an <strong>append-only</strong> record of every sensitive action
                    performed on trust data. It cannot be edited or deleted. Go to
                    <a href="{{ route('trust-accounting.audit-log.index') }}">Trust accounting → Audit log</a>.
                </p>

                <div class="guide-card">
                    <h5 class="small fw-semibold">What is logged</h5>
                    <ul class="small mb-0">
                        <li>Trust ledger voids (who voided, when, original values)</li>
                        <li>Rule 42 authority record creation and updates</li>
                        <li>Bank reconciliation matches and unmatches</li>
                        <li>Period lock and unlock actions</li>
                        <li>Trust bank account creation and changes</li>
                    </ul>
                </div>

                <div class="guide-step mt-2">
                    <div class="guide-step-num">1</div>
                    <div class="guide-step-body">
                        <strong>Filtering</strong>: Use the filter bar to narrow by table (e.g.
                        <em>Ledger rows</em>), by partial event text (e.g. type "void" to see all
                        void events), by Row ID, and by date range. Click <strong>Run</strong>.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">2</div>
                    <div class="guide-step-body">
                        <strong>Exporting</strong>: Click <strong>Export CSV</strong> to download
                        up to 10,000 rows matching the current filter. Provide this file to the
                        external examiner on request.
                    </div>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 11. PRACTICE SEQUENCES --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-sequences" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">11. Practice sequences</h5>

                <p class="small">
                    Go to <a href="{{ route('trust-accounting.practice-sequences.index') }}">Trust accounting → Sequences</a>.
                    This is a <strong>read-only</strong> screen. It shows the current highest
                    TR- and TJ- counter for each Victorian trust year. You cannot edit these
                    numbers — they are controlled by the system.
                </p>

                <div class="guide-card">
                    <h5 class="small fw-semibold">Why this matters for examiners</h5>
                    <p class="small mb-0">
                        An external examiner may ask how many trust receipts were issued in a trust year
                        and whether there are any gaps. The <em>Last sequence no.</em> column tells you
                        the highest TR- number issued for that year. If the sequential run has no gaps
                        (which the system guarantees for entries made through this CRM), you can
                        demonstrate the sequence is complete. TJ- numbers are separate so journal
                        entries never consume a TR- number.
                    </p>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 12. MONTH-END CHECKLIST --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-monthend" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">12. Month-end checklist</h5>

                <p class="small text-muted">Complete these steps in order each month (typically within 15 days of month end per Rule 48).</p>

                <div class="guide-card">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th style="width:36px;">#</th><th>Task</th><th>Where</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-muted">1</td>
                                <td>Confirm all trust entries for the month are posted (deposits, fee transfers, disbursements, refunds)</td>
                                <td class="small text-muted">Client → Accounts tab</td>
                            </tr>
                            <tr>
                                <td class="text-muted">2</td>
                                <td>Confirm every Fee Transfer has a Rule 42 authority record (check Payments journal for "—" in the Rule 42 column)</td>
                                <td class="small"><a href="{{ route('trust-accounting.reports.payments-journal') }}">Payments journal</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">3</td>
                                <td>Run trial balance as at last day of month — note total</td>
                                <td class="small"><a href="{{ route('trust-accounting.reports.trial-balance') }}">Trial balance</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">4</td>
                                <td>Enter all bank statement lines for the month</td>
                                <td class="small"><a href="{{ route('trust-accounting.reconciliation.index') }}">Reconciliation</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">5</td>
                                <td>Match every statement line to its ledger row; confirm variance = $0.00</td>
                                <td class="small"><a href="{{ route('trust-accounting.reconciliation.index') }}">Reconciliation</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">6</td>
                                <td>Export Receipts journal CSV + Payments journal CSV for the month</td>
                                <td class="small"><a href="{{ route('trust-accounting.reports.index') }}">Reports hub</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">7</td>
                                <td>Export Trial balance CSV as at end of month</td>
                                <td class="small"><a href="{{ route('trust-accounting.reports.trial-balance') }}">Trial balance</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">8</td>
                                <td>Print reconciliation screen — have principal sign the Rule 48 statement — file for 7 years</td>
                                <td class="small text-muted">Print from browser</td>
                            </tr>
                            <tr>
                                <td class="text-muted">9</td>
                                <td>Lock the period to prevent accidental changes</td>
                                <td class="small"><a href="{{ route('trust-accounting.periods.index') }}">Period locks</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="guide-tip mt-1">
                    <strong>Keeping the examination pack:</strong> The three CSVs from steps 6–7
                    plus a screenshot or print of the reconciliation screen (step 8) form the
                    core of what an external examiner will request. Store them in a dedicated
                    folder named <code>Trust-Exam-{year}-{month}</code> alongside the signed
                    Rule 48 statement.
                </div>
            </div>

            {{-- ── Footer nav ── --}}
            <div class="border-top pt-3 mt-2 d-flex gap-2 flex-wrap">
                <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-primary btn-sm">Reports hub</a>
                <a href="{{ route('trust-accounting.reconciliation.index') }}" class="btn btn-outline-secondary btn-sm">Reconciliation</a>
                <a href="{{ route('trust-accounting.periods.index') }}" class="btn btn-outline-secondary btn-sm">Period locks</a>
                <a href="{{ route('trust-accounting.audit-log.index') }}" class="btn btn-outline-secondary btn-sm">Audit log</a>
                <a href="{{ route('trust-accounting.withdrawal-authority-types.index') }}" class="btn btn-outline-secondary btn-sm">Rule 42 types</a>
            </div>

        </div>
    </section>
</div>
@endsection
