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
.guide-test           { border-left: 4px solid #0dcaf0; background: #f0fbff; padding: 10px 14px;
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
.test-pass            { display: inline-block; background: #198754; color: #fff; border-radius: 3px;
                        padding: 1px 7px; font-size: 11px; font-weight: 600; letter-spacing: .3px; }
.test-check           { color: #0d6efd; font-weight: 600; }
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
                <ul class="mb-0 mt-1">
                    <li><strong>Rule 35</strong> — receipt form and wording (trust account name)</li>
                    <li><strong>Rule 36</strong> — required fields on each trust receipt (payer, date, method, issued-by)</li>
                    <li><strong>Rule 38</strong> — immutable month-end copies of trust records</li>
                    <li><strong>Rule 39</strong> — audit trail when client or matter details change</li>
                    <li><strong>Rule 40</strong> — overdrawn ledger detection and logging</li>
                    <li><strong>Rule 42</strong> — written withdrawal authority for fee transfers</li>
                    <li><strong>Rule 43</strong> — payee, cheque and EFT details on payment records</li>
                    <li><strong>Rule 47</strong> — trust receipts and payments records</li>
                    <li><strong>Rule 48</strong> — monthly bank reconciliation</li>
                    <li><strong>Rule 52</strong> — annual client trust account statements</li>
                </ul>
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
                        <li><a href="#section-receipt-fields">Rule 36 &amp; 43 — payer / payee fields</a></li>
                        <li><a href="#section-rule42">Rule 42 — fee transfer authority</a></li>
                        <li><a href="#section-overdraw">Rule 40 — overdrawn ledger</a></li>
                        <li><a href="#section-rule39">Rule 39 — client &amp; matter field auditing</a></li>
                        <li><a href="#section-statements">Rule 52 — client trust statements</a></li>
                        <li><a href="#section-archives">Rule 38 — monthly archives &amp; auditor's pack</a></li>
                        <li><a href="#section-reports">Reports hub</a></li>
                        <li><a href="#section-trial-balance">Trial balance</a></li>
                        <li><a href="#section-journals">Receipts &amp; payments journals</a></li>
                        <li><a href="#section-reconciliation">Bank reconciliation (Rule 48)</a></li>
                        <li><a href="#section-periods">Period locks</a></li>
                        <li><a href="#section-audit">Audit log</a></li>
                        <li><a href="#section-sequences">Practice sequences</a></li>
                        <li><a href="#section-monthend">Month-end checklist</a></li>
                        <li><a href="#section-testing">Testing &amp; verification checklist</a></li>
                    </ol>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 1. OVERVIEW --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-overview" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">1. System overview</h5>

                <p>The trust module is a single, integrated compliance workflow. Here is how the pieces fit together:</p>

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
                    <span class="guide-flow-item">Monthly archive</span>
                    <span class="guide-flow-arrow">→</span>
                    <span class="guide-flow-item">Period lock</span>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-users me-2 text-primary"></i>Who can access trust admin?</h5>
                            <p class="mb-0 small">Only staff with <strong>super-admin effective privileges</strong>
                            (the checkbox in the Staff Console) can access the trust admin screens
                            (reports, reconciliation, period locks, archives, etc.). Regular staff can post
                            client ledger entries from the client detail page but cannot lock
                            periods, export reports, or create archives.</p>
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
                                <li>Select type <em>Trust Receipt (Deposit)</em>.</li>
                                <li>Enter the transaction date, amount, payment method, and a
                                    description (e.g. "Initial retainer — <em>Matter ref</em>").</li>
                                <li><strong>Payer name</strong> — enter who the money came from (Rule 36(e)).
                                    If left blank the system logs a compliance audit event.</li>
                                <li>If paying by bank transfer, enter the <strong>bank deposit reference</strong>
                                    and <strong>banking date</strong>.</li>
                                <li>If the deposit relates to an existing invoice, select it in the
                                    <em>Invoice</em> field (allocates the trust receipt).</li>
                                <li>Click Save. A <strong>TR-</strong> reference is issued and a
                                    receipt PDF (naming the staff member who issued it) is generated.</li>
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
                                    and fill in the notice date. See <a href="#section-rule42">Section 5</a>
                                    for details.</li>
                                <li>Click Save. The ledger balance reduces immediately. The system
                                    blocks you from transferring more than the current funds held.</li>
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
                                <li>Select type <em>Disbursement (Trust Payment)</em>.</li>
                                <li>Describe exactly what the payment is for
                                    (e.g. "Barrister fee — J. Smith — Brief ref 123").</li>
                                <li>Enter the <strong>payee name</strong> (who is being paid — Rule 43).</li>
                                <li>Select payment method. If <em>Cheque</em>, enter the cheque number.
                                    If <em>Bank Transfer / EFT</em>, enter account name, BSB and account
                                    number (Rule 43).</li>
                            </ol>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-undo-alt me-2" style="color:#6f42c1;"></i>Refund to client</h5>
                            <ol class="small mb-0">
                                <li>Select type <em>Refund to Client</em>.</li>
                                <li>Enter the date and amount. The running balance will decrease.</li>
                                <li>Enter the <strong>payee name</strong> (client receiving the refund)
                                    and payment details (cheque or EFT).</li>
                                <li>Ensure the refund is backed by a bank payment before posting.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="guide-tip mt-1">
                    <strong>Viewing the ledger:</strong> The Trust Account Ledger section on the
                    Accounts tab shows a running balance, transaction type icons, and receipt
                    numbers. Voided rows appear with strikethrough. A green tick icon next to
                    the date means the receipt has been verified. Use the <strong>Trust Statement</strong>
                    button (top of the Accounts tab, visible when a matter is selected) to generate
                    a Rule 52 statement for the client in that matter at any time.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 4. RECEIPT FIELDS (RULE 36 & 43) --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-receipt-fields" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">4. Rule 36 &amp; 43 — payer / payee &amp; payment fields</h5>

                <div class="guide-law mb-3">
                    <strong>Rule 36</strong> requires each trust receipt to record: the date, amount, form of payment,
                    the name of the person from whom money was received (payer), the matter reference, and the name of
                    the person receipting the money.<br/>
                    <strong>Rule 43</strong> requires payments records to show the name of the person to whom payment
                    was made (payee) and, for cheques, the cheque number; for EFT, the account name, BSB and account number.
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-arrow-down me-2 text-success"></i>Deposit (money-in) fields</h5>
                            <table class="table table-sm table-bordered mb-0 small">
                                <thead class="table-light"><tr><th>Field</th><th>Purpose</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>Payer / Payee</strong> column — top input</td><td>Name of person paying money into trust (Rule 36(e)). Logged as an audit event if omitted.</td></tr>
                                    <tr><td><strong>Bank / Cheque ref</strong></td><td>Bank deposit slip reference for reconciliation.</td></tr>
                                    <tr><td><strong>Date / BSB</strong> — top input</td><td>Banking date — the date funds cleared the bank (may differ from transaction date).</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="guide-card">
                            <h5><i class="fas fa-arrow-up me-2 text-danger"></i>Payment (money-out) fields</h5>
                            <table class="table table-sm table-bordered mb-0 small">
                                <thead class="table-light"><tr><th>Field</th><th>Purpose</th></tr></thead>
                                <tbody>
                                    <tr><td><strong>Payer / Payee</strong> column — bottom input</td><td>Name of person receiving trust funds — payee (Rule 43).</td></tr>
                                    <tr><td><strong>Bank / Cheque ref</strong> — bottom input</td><td>Cheque number (visible when <em>Cheque</em> is selected as payment method).</td></tr>
                                    <tr><td><strong>Date / BSB</strong> — bottom input</td><td>EFT BSB of recipient account (visible when <em>Bank Transfer / EFT</em> is selected).</td></tr>
                                    <tr><td><strong>EFT details</strong> column</td><td>EFT account name and account number (Rule 43).</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="guide-tip mt-1">
                    <strong>Dynamic fields:</strong> The entry form shows only the fields relevant to the selected
                    transaction type and payment method. Deposit rows show payer and banking fields;
                    Disbursement / Refund / Fee Transfer rows show payee, cheque or EFT fields.
                    EFT fields appear only when <em>Bank Transfer / EFT</em> is chosen.
                    Cheque number appears only when <em>Cheque</em> is chosen.
                </div>

                <div class="guide-card mt-2">
                    <h5><i class="fas fa-file-pdf me-2 text-danger"></i>Trust receipt PDF (Rule 35 &amp; 36)</h5>
                    <p class="small mb-0">
                        When a trust entry is saved, a PDF receipt is automatically generated naming
                        <strong>Bansal Lawyers law practice trust account</strong> (Rule 35 — the receipt must
                        identify the law practice's trust account, not just "our trust account").
                        The receipt also shows <strong>Received by:</strong> with the name of the staff member
                        who posted the entry (Rule 36(i)). The PDF is attached to the client record and can be
                        emailed or downloaded at any time.
                    </p>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 5. RULE 42 --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-rule42" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">5. Rule 42 — fee transfer authority</h5>

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
            {{-- 6. RULE 40 — OVERDRAWN LEDGER --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-overdraw" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">6. Rule 40 — overdrawn ledger report</h5>

                <div class="guide-law mb-3">
                    <strong>Rule 40</strong> — A law practice must not overdraw a trust ledger account.
                    If an overdraw occurs the practice must remedy it immediately and report it. The system
                    detects and logs overdraws automatically but does <strong>not</strong> block Disbursements
                    or Refunds that cause a negative balance (only Fee Transfers are hard-blocked). This allows
                    you to correct bank timing differences while maintaining a complete audit record.
                </div>

                <div class="guide-step">
                    <div class="guide-step-num">1</div>
                    <div class="guide-step-body">
                        Go to <a href="{{ route('trust-accounting.reports.overdrawn-ledger') }}">Reports → Overdrawn ledger</a>.
                        Set the date range and click <strong>Run</strong>.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">2</div>
                    <div class="guide-step-body">
                        The table lists every ledger row where the <strong>running balance</strong> went below zero.
                        Columns show: transaction date, reference number, type, client, matter, withdrawal amount, and
                        resulting negative balance.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">3</div>
                    <div class="guide-step-body">
                        Click <strong>CSV</strong> to download the overdrawn ledger report (UTF-8 BOM, opens in Excel).
                        The CSV also includes the corresponding audit log events for each overdraw.
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-num">4</div>
                    <div class="guide-step-body">
                        For every row on this report: investigate immediately, correct the cause (e.g. post a
                        missing deposit, correct an incorrect disbursement date), and document the remedy.
                        The audit log records the overdraw event and any subsequent void/correction actions.
                    </div>
                </div>

                <div class="guide-warning mt-1">
                    <strong>Fee Transfers are hard-blocked</strong> — the system will refuse to save a Fee Transfer
                    that would exceed current funds held. Disbursements and Refunds are logged-only (audit event
                    <code>overdrawn_transaction_posted</code>) to accommodate legitimate timing differences
                    (e.g. a deposit in transit). Any overdrawn state must be remedied and reported to the VLSB+C.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 7. RULE 39 — CLIENT & MATTER FIELD AUDITING --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-rule39" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">7. Rule 39 — client &amp; matter field auditing</h5>

                <div class="guide-law mb-3">
                    <strong>Rule 39</strong> — Trust records must accurately identify the person for whom trust money
                    is held. If a client's name, address, or matter reference changes after trust money has been received,
                    that change must be traceable. The system automatically records these changes in the trust audit log.
                </div>

                <div class="guide-card">
                    <h5 class="small fw-semibold">What is automatically audited</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="small fw-semibold mb-1"><i class="fas fa-user me-1"></i> Client record changes</p>
                            <ul class="small mb-0">
                                <li>First name</li>
                                <li>Last name</li>
                                <li>Address</li>
                                <li>City / Suburb</li>
                                <li>State</li>
                                <li>Postcode</li>
                                <li>Country</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <p class="small fw-semibold mb-1"><i class="fas fa-folder me-1"></i> Matter record changes</p>
                            <ul class="small mb-0">
                                <li>Matter reference number (<code>client_unique_matter_no</code>)</li>
                                <li>Matter description / case detail</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <p class="small mt-2">
                    Changes are recorded in the <strong>Audit log</strong> with event type
                    <code>field_updated</code>, the table name (<code>admins</code> or <code>client_matters</code>),
                    the field that changed, the old value, and the new value. To view these events:
                    go to <a href="{{ route('trust-accounting.audit-log.index') }}">Audit log</a> and
                    filter by table <em>admins</em> or <em>client_matters</em> and event <em>field_updated</em>.
                </p>

                <div class="guide-tip">
                    <strong>No action needed from staff</strong> — this auditing is fully automatic. Every time
                    a client's name or address is saved in the CRM, the system checks whether it changed and records
                    the before/after values. You do not need to do anything differently.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 8. RULE 52 — CLIENT TRUST STATEMENTS --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-statements" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">8. Rule 52 — client trust statements</h5>

                <div class="guide-law mb-3">
                    <strong>Rule 52</strong> — A law practice must give each person for whom it holds trust money a
                    <strong>trust account statement</strong> at least once a year (as at 30 June) and at the conclusion
                    of the matter. The statement must show all receipts and payments during the period and the
                    balance held.
                </div>

                <div class="guide-card">
                    <h5><i class="fas fa-file-alt me-2 text-primary"></i>On-demand statement (any time)</h5>
                    <ol class="small mb-0">
                        <li>From the client's Accounts tab, select the matter from the dropdown.</li>
                        <li>Click the <strong>Trust Statement</strong> button. A PDF opens in a new tab
                            showing all trust transactions for that matter.</li>
                        <li>Alternatively, go to
                            <a href="{{ route('trust-accounting.statements.index') }}">Reports → Trust statements</a>,
                            enter the client ID and matter ID, optionally set a date range, and click
                            <strong>Generate PDF</strong>.</li>
                    </ol>
                </div>

                <div class="guide-card">
                    <h5><i class="fas fa-calendar-alt me-2 text-warning"></i>30 June annual batch run</h5>
                    <ol class="small mb-0">
                        <li>Go to <a href="{{ route('trust-accounting.statements.annual') }}">Reports → Trust statements → 30 June batch</a>.</li>
                        <li>The page lists every matter with a non-zero trust balance as at 30 June this year.
                            Matters marked <strong>Exempt</strong> have a zero balance and no trust activity in the
                            past 12 months — you may skip sending a statement to these.</li>
                        <li>For each non-exempt matter: click <strong>PDF</strong> to generate the statement,
                            send it to the client by email or post, then click <strong>Mark sent</strong>.</li>
                        <li>Marked matters show the date the statement was recorded as sent. This satisfies
                            Rule 52 for the year.</li>
                    </ol>
                    <div class="guide-warning mt-2">
                        <strong>Deadline:</strong> Annual statements must be sent by <strong>30 September</strong>
                        (within 3 months of 30 June). The batch screen shows the <em>Last sent</em> date so you
                        can track which matters are outstanding.
                    </div>
                </div>

                <div class="guide-tip">
                    The statement PDF header names <strong>Bansal Lawyers law practice trust account</strong>
                    (Rule 35) and includes the client's address, matter reference, period dates, opening balance,
                    all transactions (with date, reference, type and description), and closing balance.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 9. RULE 38 — MONTHLY ARCHIVES --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-archives" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">9. Rule 38 — monthly archives &amp; auditor's pack</h5>

                <div class="guide-law mb-3">
                    <strong>Rule 38</strong> — A law practice must retain trust records for at least 7 years.
                    Month-end copies of the journals and trial balance must be kept in a form that cannot be altered.
                    The monthly archive feature creates immutable CSV snapshots of each report.
                </div>

                <div class="guide-card">
                    <h5><i class="fas fa-archive me-2 text-secondary"></i>Creating a monthly archive</h5>
                    <ol class="small mb-0">
                        <li>Go to <a href="{{ route('trust-accounting.archives.index') }}">Reports → Monthly archives</a>.</li>
                        <li>Enter the <strong>Year</strong> and <strong>Month</strong> to archive (e.g. 2026 / 5 for May 2026).
                            You must have locked the period first — locking prevents further changes to entries in that month.</li>
                        <li>Click <strong>Archive month reports</strong>. The system creates three CSV snapshots:
                            <ul class="mt-1">
                                <li><code>receipts_journal</code> — all trust receipts for the month</li>
                                <li><code>payments_journal</code> — all trust payments for the month</li>
                                <li><code>trial_balance</code> — balances held as at end of month</li>
                            </ul>
                        </li>
                        <li>Each archive row shows who prepared it and when. Click <strong>Download</strong>
                            to retrieve any archived CSV at any time.</li>
                        <li>Attempting to archive a month that is already archived is silently skipped
                            (idempotent) — you will see a message saying how many were created vs. skipped.</li>
                    </ol>
                </div>

                <div class="guide-card">
                    <h5><i class="fas fa-file-archive me-2 text-secondary"></i>Auditor's pack (ZIP download)</h5>
                    <p class="small mb-1">
                        Go to <a href="{{ route('trust-accounting.reports.index') }}">Reports hub</a>
                        and use the <strong>Auditor's pack</strong> card. Enter a date range and click
                        <strong>Download ZIP</strong>. The ZIP contains:
                    </p>
                    <ul class="small mb-0">
                        <li>Receipts journal CSV for the range</li>
                        <li>Payments journal CSV for the range (includes payee, cheque, EFT columns)</li>
                        <li>Trial balance CSV as at the end date</li>
                        <li>Overdrawn ledger CSV for the range</li>
                    </ul>
                    <div class="guide-tip mt-2">
                        <strong>External examination:</strong> Provide the ZIP from <em>Auditor's pack</em> plus
                        the signed Rule 48 reconciliation statements as the primary supporting pack for the examiner.
                        Name the ZIP file <code>TrustExamPack-{from}-to-{to}</code> for easy identification.
                    </div>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 10. REPORTS HUB --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-reports" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">10. Reports hub</h5>

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
                            CSV includes payer name, banking date, bank reference, and invoice reference (Rule 36).</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="guide-card h-100">
                            <h5><i class="fas fa-arrow-up me-2 text-danger"></i>Payments journal</h5>
                            <p class="small mb-0">All money-out movements in a date range.
                            CSV includes payee name, cheque number, EFT details (Rule 43), and full Rule 42 authority columns.</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="guide-card h-100">
                            <h5><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Overdrawn ledger</h5>
                            <p class="small mb-0">Rule 40 — any ledger rows with a negative running balance.
                            Must be cleared immediately and reported.</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="guide-card h-100">
                            <h5><i class="fas fa-file-alt me-2 text-primary"></i>Trust statements</h5>
                            <p class="small mb-0">Rule 52 — on-demand PDF or 30 June annual batch run
                            for all matters with funds held.</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="guide-card h-100">
                            <h5><i class="fas fa-archive me-2 text-secondary"></i>Monthly archives</h5>
                            <p class="small mb-0">Rule 38 — immutable month-end CSV copies of all three journals.
                            Download individual CSVs or the full auditor's pack ZIP.</p>
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
            {{-- 11. TRIAL BALANCE --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-trial-balance" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">11. Trial balance</h5>

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
            {{-- 12. JOURNALS --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-journals" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">12. Receipts &amp; Payments journals</h5>

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
                        month you are reviewing. Click <strong>Run</strong> to view on screen
                        (100 rows per page), or <strong>CSV</strong> to download the full set.
                    </div>
                </div>

                <div class="guide-card mt-2">
                    <h5 class="small fw-semibold">Payments journal CSV columns (including Rule 43)</h5>
                    <table class="table table-sm table-bordered mb-0 small">
                        <thead class="table-light">
                            <tr><th>Column</th><th>Rule</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Trans date</td><td></td><td>Date of the ledger transaction (as stored)</td></tr>
                            <tr><td>Receipt no.</td><td></td><td>Unique TR- or TJ- reference</td></tr>
                            <tr><td>Type</td><td></td><td>Fee Transfer / Disbursement / Refund</td></tr>
                            <tr><td>Client / Matter</td><td></td><td>Client reference and matter number</td></tr>
                            <tr><td>Amount</td><td></td><td>Amount withdrawn from trust</td></tr>
                            <tr><td>Method</td><td></td><td>Payment method recorded at posting</td></tr>
                            <tr><td>Invoice ref</td><td></td><td>Invoice number for fee transfers</td></tr>
                            <tr><td><strong>Payee</strong></td><td>R.43</td><td>Name of person/entity paid from trust</td></tr>
                            <tr><td><strong>Cheque no.</strong></td><td>R.43</td><td>Cheque number if paid by cheque</td></tr>
                            <tr><td><strong>EFT account</strong></td><td>R.43</td><td>Recipient bank account name</td></tr>
                            <tr><td><strong>EFT BSB</strong></td><td>R.43</td><td>Recipient BSB</td></tr>
                            <tr><td><strong>EFT acct no.</strong></td><td>R.43</td><td>Recipient account number</td></tr>
                            <tr><td>Rule 42 type</td><td>R.42</td><td>Authority type label (fee transfers only)</td></tr>
                            <tr><td>Rule 42 notice date</td><td>R.42</td><td>Date client was notified</td></tr>
                            <tr><td>Rule 42 notes</td><td>R.42</td><td>Free-text authority document reference</td></tr>
                            <tr><td>Supervisor override</td><td>R.42</td><td>Yes / blank</td></tr>
                            <tr><td>Override reason</td><td>R.42</td><td>Stated reason for override</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 13. BANK RECONCILIATION --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-reconciliation" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">13. Bank reconciliation <small class="text-muted fw-normal">(Rule 48 — monthly)</small></h5>

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
                        balance. Print or screenshot the screen for your signed Rule 48 reconciliation statement.
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
            {{-- 14. PERIOD LOCKS --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-periods" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">14. Period locks</h5>

                <p class="small">
                    After completing the monthly reconciliation, lock the period to prevent staff
                    from accidentally altering historical entries — and to meet Rule 38's immutability requirement
                    before archiving.
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
            {{-- 15. AUDIT LOG --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-audit" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">15. Audit log</h5>

                <p class="small">
                    The audit log is an <strong>append-only</strong> record of every sensitive action
                    performed on trust data. It cannot be edited or deleted. Go to
                    <a href="{{ route('trust-accounting.audit-log.index') }}">Trust accounting → Audit log</a>.
                </p>

                <div class="guide-card">
                    <h5 class="small fw-semibold">What is logged</h5>
                    <table class="table table-sm table-bordered mb-0 small">
                        <thead class="table-light"><tr><th>Event</th><th>Triggered when</th></tr></thead>
                        <tbody>
                            <tr><td><code>void_entry</code></td><td>A trust ledger row is voided</td></tr>
                            <tr><td><code>metadata_updated</code></td><td>Description, payment method, payer/payee, EFT or cheque details edited on a ledger row</td></tr>
                            <tr><td><code>deposit_posted_without_payer_name</code></td><td>A deposit is saved without a payer name (Rule 36 soft compliance warning)</td></tr>
                            <tr><td><code>overdrawn_transaction_posted</code></td><td>A Disbursement or Refund results in a negative ledger balance (Rule 40)</td></tr>
                            <tr><td><code>field_updated</code> (admins)</td><td>A client's name or address is changed in the CRM (Rule 39)</td></tr>
                            <tr><td><code>field_updated</code> (client_matters)</td><td>A matter reference or description is changed (Rule 39)</td></tr>
                            <tr><td><code>rule42_authority_created</code></td><td>A Rule 42 withdrawal authority record is created for a fee transfer</td></tr>
                            <tr><td><code>period_locked / period_unlocked</code></td><td>A trust accounting period is locked or unlocked</td></tr>
                            <tr><td><code>reconciliation_match / unmatch</code></td><td>A bank statement line is matched or unmatched to a ledger row</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="guide-step mt-2">
                    <div class="guide-step-num">1</div>
                    <div class="guide-step-body">
                        <strong>Filtering</strong>: Use the filter bar to narrow by table (e.g.
                        <em>admins</em>, <em>account_client_receipts</em>), by partial event text
                        (e.g. type "overdrawn"), by Row ID, and by date range. Click <strong>Run</strong>.
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
            {{-- 16. PRACTICE SEQUENCES --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-sequences" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">16. Practice sequences</h5>

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
            {{-- 17. MONTH-END CHECKLIST --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-monthend" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">17. Month-end checklist</h5>

                <p class="small text-muted">Complete these steps in order each month (within 15 days of month end per Rule 48).</p>

                <div class="guide-card">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th style="width:36px;">#</th><th>Task</th><th>Where</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-muted">1</td>
                                <td>Confirm all trust entries for the month are posted (deposits, fee transfers, disbursements, refunds), with payer/payee names and payment details captured</td>
                                <td class="small text-muted">Client → Accounts tab</td>
                            </tr>
                            <tr>
                                <td class="text-muted">2</td>
                                <td>Check the <strong>Overdrawn ledger report</strong> for the month — any negative balances must be investigated and corrected before proceeding</td>
                                <td class="small"><a href="{{ route('trust-accounting.reports.overdrawn-ledger') }}">Overdrawn ledger</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">3</td>
                                <td>Confirm every Fee Transfer has a Rule 42 authority record (check Payments journal for "—" in the Rule 42 column)</td>
                                <td class="small"><a href="{{ route('trust-accounting.reports.payments-journal') }}">Payments journal</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">4</td>
                                <td>Run trial balance as at last day of month — note total</td>
                                <td class="small"><a href="{{ route('trust-accounting.reports.trial-balance') }}">Trial balance</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">5</td>
                                <td>Enter all bank statement lines for the month</td>
                                <td class="small"><a href="{{ route('trust-accounting.reconciliation.index') }}">Reconciliation</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">6</td>
                                <td>Match every statement line to its ledger row; confirm variance = $0.00</td>
                                <td class="small"><a href="{{ route('trust-accounting.reconciliation.index') }}">Reconciliation</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">7</td>
                                <td>Print reconciliation screen — have principal sign the Rule 48 statement — file for 7 years</td>
                                <td class="small text-muted">Print from browser</td>
                            </tr>
                            <tr>
                                <td class="text-muted">8</td>
                                <td>Lock the period to prevent accidental changes</td>
                                <td class="small"><a href="{{ route('trust-accounting.periods.index') }}">Period locks</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">9</td>
                                <td>Create the <strong>monthly archive</strong> for the locked month (receipts journal, payments journal, trial balance CSVs)</td>
                                <td class="small"><a href="{{ route('trust-accounting.archives.index') }}">Monthly archives</a></td>
                            </tr>
                            <tr>
                                <td class="text-muted">10</td>
                                <td>Download the <strong>Auditor's pack ZIP</strong> for the month and store it in the examination file folder</td>
                                <td class="small"><a href="{{ route('trust-accounting.reports.index') }}">Reports hub</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="guide-tip mt-1">
                    <strong>Annual obligation (30 June):</strong> Run the
                    <a href="{{ route('trust-accounting.statements.annual') }}">30 June batch statements</a>
                    and mark each as sent by 30 September. Keep a copy of each PDF statement on file.
                    The batch screen tracks which matters still need a statement sent.
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────── --}}
            {{-- 18. TESTING & VERIFICATION --}}
            {{-- ───────────────────────────────────────────────────────── --}}
            <div id="section-testing" class="guide-section section-anchor">
                <h5 class="border-bottom pb-2 mb-3">18. Testing &amp; verification checklist</h5>

                <p class="small text-muted mb-3">
                    Use these manual test steps to confirm each compliance feature is working correctly
                    after deployment or after any system update. Each test is self-contained — you can
                    run them in any order. Use a test client record to avoid polluting real data.
                </p>

                {{-- Test 1 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T1</span>Rule 35 &amp; 36 — Receipt PDF wording and staff name</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> The trust receipt PDF names the practice trust account correctly
                        and shows who issued the receipt.
                    </div>
                    <ol class="small mb-0">
                        <li>Log in as a staff member (note the staff member's name).</li>
                        <li>Open a test client → Accounts tab → click <strong>Trust Account Entry</strong>.</li>
                        <li>Select type <em>Trust Receipt</em>, enter any amount, date, and description. Click <strong>Save</strong>.</li>
                        <li>Click the PDF icon next to the new ledger entry to open the receipt PDF.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected results:</strong></p>
                    <ul class="small mb-0">
                        <li>The receipt acknowledgement text reads: <em>"…received the above amount into the <strong>Bansal Lawyers law practice trust account</strong>…"</em></li>
                        <li>The document info block shows <em>Received by: [staff member's full name]</em></li>
                        <li>A TR- reference number is visible on the receipt</li>
                    </ul>
                </div>

                {{-- Test 2 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T2</span>Rule 36(e) — Payer name audit event when omitted</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> A deposit saved without a payer name creates an audit log entry.
                    </div>
                    <ol class="small mb-0">
                        <li>Post a trust deposit on a test client, leaving the <strong>Payer / Payee</strong> field blank.</li>
                        <li>Go to <a href="{{ route('trust-accounting.audit-log.index') }}">Audit log</a>.</li>
                        <li>Filter by event text: <code>deposit_posted_without_payer_name</code>. Click <strong>Run</strong>.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected result:</strong></p>
                    <ul class="small mb-0">
                        <li>A new audit row appears with event <code>deposit_posted_without_payer_name</code>
                            and context <em>"Rule 36(e) payer name not captured at posting"</em> for the row ID just created.</li>
                    </ul>
                </div>

                {{-- Test 3 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T3</span>Rule 43 — Payee, cheque &amp; EFT fields on payments</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> Cheque and EFT fields appear for withdrawal types and are saved and exported correctly.
                    </div>
                    <ol class="small mb-0">
                        <li>Post a trust deposit first so there are funds to withdraw.</li>
                        <li>Add a new trust entry, type <em>Disbursement</em>. Observe that the <em>Payer / Payee</em> cell shows
                            the <strong>Payee name</strong> input (payer name input should be hidden).</li>
                        <li>Select payment method <em>Cheque</em>. Confirm the <strong>Cheque no.</strong> input appears
                            in the Bank / Cheque ref cell; EFT fields remain hidden.</li>
                        <li>Enter payee name <code>Test Barrister Pty Ltd</code> and cheque number <code>000123</code>. Save.</li>
                        <li>Now add another disbursement, select <em>Bank Transfer / EFT</em>. Confirm the <strong>BSB</strong>,
                            <strong>EFT account name</strong> and <strong>EFT account number</strong> fields appear. Enter values and save.</li>
                        <li>Go to <a href="{{ route('trust-accounting.reports.payments-journal') }}">Payments journal</a>,
                            set date range to today, click <strong>CSV</strong>.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected results:</strong></p>
                    <ul class="small mb-0">
                        <li>Cheque disbursement: CSV columns <em>Payee</em> = <code>Test Barrister Pty Ltd</code>,
                            <em>Cheque no.</em> = <code>000123</code>; EFT columns blank.</li>
                        <li>EFT disbursement: CSV columns <em>EFT account</em>, <em>EFT BSB</em>, <em>EFT acct no.</em> filled;
                            Cheque no. blank.</li>
                    </ul>
                </div>

                {{-- Test 4 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T4</span>Rule 40 — Overdrawn ledger detection</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> A disbursement that causes a negative balance is logged and appears on the overdrawn report.
                    </div>
                    <div class="guide-warning mb-2">Use a test client with a zero or small balance to avoid affecting real client funds.</div>
                    <ol class="small mb-0">
                        <li>On a test client with <strong>$0</strong> trust balance (or post a $10 deposit first),
                            post a Disbursement for <strong>$50</strong> (more than the balance). Save.</li>
                        <li>Go to <a href="{{ route('trust-accounting.reports.overdrawn-ledger') }}">Overdrawn ledger report</a>,
                            set date range to today. Click <strong>Run</strong>.</li>
                        <li>Go to <a href="{{ route('trust-accounting.audit-log.index') }}">Audit log</a>,
                            filter event: <code>overdrawn_transaction_posted</code>.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected results:</strong></p>
                    <ul class="small mb-0">
                        <li>Overdrawn report shows the disbursement row with a red negative balance.</li>
                        <li>Audit log shows <code>overdrawn_transaction_posted</code> for the same row ID with the prior balance, withdrawal amount, and resulting balance.</li>
                        <li><em>Fee Transfer</em> of the same amount should have been <strong>blocked</strong> by the system with an error message — confirm by attempting it.</li>
                    </ul>
                </div>

                {{-- Test 5 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T5</span>Rule 39 — Client field change auditing</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> Changing a client's name or address creates an audit log entry automatically.
                    </div>
                    <ol class="small mb-0">
                        <li>Open a test client's personal details and change the <strong>last name</strong> (e.g. append " Test").</li>
                        <li>Save the change.</li>
                        <li>Go to <a href="{{ route('trust-accounting.audit-log.index') }}">Audit log</a>,
                            filter table: <code>admins</code>, event: <code>field_updated</code>. Click <strong>Run</strong>.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected result:</strong></p>
                    <ul class="small mb-0">
                        <li>A new audit row appears with table <code>admins</code>, event <code>field_updated</code>,
                            field <code>last_name</code>, old value = original name, new value = new name,
                            context <em>"Rule 39 client field change"</em>.</li>
                        <li>Revert the name change — a second audit row should appear recording the reversion.</li>
                    </ul>
                </div>

                {{-- Test 6 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T6</span>Rule 39 — Matter field change auditing</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> Changing a matter reference or description creates an audit entry.
                    </div>
                    <ol class="small mb-0">
                        <li>Open a test client → Matters → edit a matter and change the <strong>matter description / case detail</strong>.</li>
                        <li>Save the change.</li>
                        <li>Go to <a href="{{ route('trust-accounting.audit-log.index') }}">Audit log</a>,
                            filter table: <code>client_matters</code>, event: <code>field_updated</code>.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected result:</strong></p>
                    <ul class="small mb-0">
                        <li>Audit row with table <code>client_matters</code>, event <code>field_updated</code>,
                            field <code>case_detail</code>, old and new values, context <em>"Rule 39 matter field change"</em>.</li>
                    </ul>
                </div>

                {{-- Test 7 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T7</span>Rule 52 — On-demand trust statement PDF</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> A trust statement PDF can be generated for a client and matter.
                    </div>
                    <ol class="small mb-0">
                        <li>Open a test client with at least one trust entry → Accounts tab.</li>
                        <li>Select a matter from the matter dropdown (top of the Accounts tab).</li>
                        <li>Click the <strong>Trust Statement</strong> button. A PDF should open in a new tab.</li>
                        <li>Alternatively go to <a href="{{ route('trust-accounting.statements.index') }}">Reports → Trust statements</a>,
                            enter the client's <code>admins.id</code> and the <code>client_matters.id</code>, click <strong>Generate PDF</strong>.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected results:</strong></p>
                    <ul class="small mb-0">
                        <li>PDF header shows <em>Bansal Lawyers — law practice trust account</em> and the client's name and address.</li>
                        <li>Matter reference is shown.</li>
                        <li>Table lists all trust transactions with dates, TR- references, types, descriptions, deposit/payment amounts, and running balance.</li>
                        <li>Opening balance and closing balance summary is shown at the top.</li>
                        <li>Footer cites <em>"Rule 52 of the Legal Profession Uniform General Rules 2015"</em>.</li>
                    </ul>
                </div>

                {{-- Test 8 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T8</span>Rule 52 — 30 June annual batch screen</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> The annual batch page lists matters with trust funds and allows marking as sent.
                    </div>
                    <ol class="small mb-0">
                        <li>Go to <a href="{{ route('trust-accounting.statements.annual') }}">Reports → Trust statements → 30 June batch</a>.</li>
                        <li>Confirm the page lists all matters with non-zero trust balances as at 30 June of the current year.</li>
                        <li>Check that matters with zero balance and no activity in 12 months are labelled <strong>Exempt</strong>.</li>
                        <li>For a non-exempt matter, click <strong>PDF</strong> — confirm the statement opens.</li>
                        <li>Click <strong>Mark sent</strong> for that matter.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected results:</strong></p>
                    <ul class="small mb-0">
                        <li>After marking sent, the <em>Last sent</em> column shows today's date for that matter.</li>
                        <li>The <strong>Mark sent</strong> button remains available (to re-mark if needed).</li>
                    </ul>
                </div>

                {{-- Test 9 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T9</span>Rule 38 — Monthly archive creation and download</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> Monthly archives are created, stored, and downloadable.
                    </div>
                    <ol class="small mb-0">
                        <li>Lock a test period first: go to <a href="{{ route('trust-accounting.periods.index') }}">Period locks</a>
                            and lock a past month (e.g. April 2026).</li>
                        <li>Go to <a href="{{ route('trust-accounting.archives.index') }}">Monthly archives</a>.</li>
                        <li>Enter year <code>2026</code> and month <code>4</code>. Click <strong>Archive month reports</strong>.</li>
                        <li>Confirm the success message: <em>"Archived 3 report(s). Skipped 0 already archived."</em></li>
                        <li>The table now shows three rows for April 2026: <code>receipts_journal</code>, <code>payments_journal</code>, <code>trial_balance</code>.</li>
                        <li>Click <strong>Download</strong> on one row — confirm a CSV file downloads.</li>
                        <li>Attempt to archive the same month again — confirm: <em>"Archived 0. Skipped 3 already archived."</em> (idempotent).</li>
                    </ol>
                </div>

                {{-- Test 10 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T10</span>Auditor's pack ZIP download</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> The auditor's pack downloads as a ZIP containing all four CSVs.
                    </div>
                    <ol class="small mb-0">
                        <li>Go to <a href="{{ route('trust-accounting.reports.index') }}">Reports hub</a>.</li>
                        <li>In the <strong>Auditor's pack</strong> card, enter a date range that includes known trust entries
                            (e.g. from 2026-01-01 to 2026-05-31). Click <strong>Download ZIP</strong>.</li>
                        <li>Open the downloaded ZIP file.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected results:</strong></p>
                    <ul class="small mb-0">
                        <li>ZIP contains four CSV files: receipts journal, payments journal, trial balance, overdrawn ledger.</li>
                        <li>Each CSV opens in Excel with correct UTF-8 encoding (no garbled characters).</li>
                        <li>Payments journal CSV includes <em>Payee</em>, <em>Cheque no.</em>, <em>EFT account</em>, <em>EFT BSB</em>, <em>EFT acct no.</em> columns.</li>
                        <li>Receipts journal CSV includes <em>Payer name</em>, <em>Bank ref</em>, <em>Banking date</em> columns.</li>
                    </ul>
                </div>

                {{-- Test 11 --}}
                <div class="guide-card">
                    <h5><span class="badge bg-primary me-2">T11</span>Edit ledger entry — payer / payee fields persist</h5>
                    <div class="guide-test mb-2">
                        <strong>What to check:</strong> The edit modal correctly loads and saves payer/payee/EFT fields.
                    </div>
                    <ol class="small mb-0">
                        <li>On the client Accounts tab, find a trust ledger entry. Click the dropdown arrow → <strong>Edit Entry</strong>.</li>
                        <li>Confirm the <em>Payer / source</em> field is populated if a payer name was entered at posting.</li>
                        <li>Change the payee name to <code>Updated Payee</code>, change payment method to <em>EFT</em>
                            — confirm the EFT fields appear. Enter BSB <code>063-123</code> and save.</li>
                        <li>Re-open the edit modal for the same entry.</li>
                    </ol>
                    <p class="small mt-2 mb-1"><strong>Expected results:</strong></p>
                    <ul class="small mb-0">
                        <li>Payee shows <code>Updated Payee</code>; EFT BSB shows <code>063-123</code>.</li>
                        <li>Audit log shows a <code>metadata_updated</code> event for <code>payee_name</code>
                            and <code>eft_bsb</code> with old and new values.</li>
                    </ul>
                </div>

                <div class="guide-tip mt-2">
                    <strong>After running all tests:</strong> Check the audit log for any unexpected
                    events, confirm the overdrawn ledger report is clear (or all entries are explained),
                    and void any test transactions to clean up — voided rows appear in strikethrough on the
                    client ledger and are excluded from balances and journals, but remain in the audit trail.
                </div>
            </div>

            {{-- ── Footer nav ── --}}
            <div class="border-top pt-3 mt-2 d-flex gap-2 flex-wrap">
                <a href="{{ route('trust-accounting.reports.index') }}" class="btn btn-outline-primary btn-sm">Reports hub</a>
                <a href="{{ route('trust-accounting.reconciliation.index') }}" class="btn btn-outline-secondary btn-sm">Reconciliation</a>
                <a href="{{ route('trust-accounting.periods.index') }}" class="btn btn-outline-secondary btn-sm">Period locks</a>
                <a href="{{ route('trust-accounting.archives.index') }}" class="btn btn-outline-secondary btn-sm">Archives</a>
                <a href="{{ route('trust-accounting.statements.index') }}" class="btn btn-outline-secondary btn-sm">Statements</a>
                <a href="{{ route('trust-accounting.audit-log.index') }}" class="btn btn-outline-secondary btn-sm">Audit log</a>
                <a href="{{ route('trust-accounting.withdrawal-authority-types.index') }}" class="btn btn-outline-secondary btn-sm">Rule 42 types</a>
            </div>

        </div>
    </section>
</div>
@endsection
