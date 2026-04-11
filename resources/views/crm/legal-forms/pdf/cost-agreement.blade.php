<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Costs Agreement</title>
    <style>
        @page { margin: 2cm 2.5cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #333; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1a3a5c; padding-bottom: 15px; }
        .header h1 { font-size: 16pt; color: #1a3a5c; margin: 0 0 5px; }
        .intro { margin-bottom: 20px; font-size: 10pt; }
        .section { margin-bottom: 15px; }
        .section-title { font-size: 11pt; font-weight: bold; color: #1a3a5c; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 10px; }
        table.details-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.details-table td { padding: 6px 10px; vertical-align: top; border: 1px solid #ddd; }
        table.details-table td.label { width: 30%; background: #f7f9fb; font-weight: bold; color: #444; }
        table.details-table td.value { width: 70%; }
        .scope-box { border: 1px solid #ddd; padding: 10px 15px; background: #fafafa; min-height: 50px; white-space: pre-line; }
        .costs-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .costs-table td, .costs-table th { padding: 6px 10px; border: 1px solid #ddd; }
        .costs-table th { background: #f0f4f8; text-align: left; font-weight: bold; color: #1a3a5c; }
        .costs-table .amount { text-align: right; font-weight: bold; }
        .total-row td { background: #1a3a5c; color: #fff; font-weight: bold; }
        .bank-details { background: #f7f9fb; border: 1px solid #d0d8e0; padding: 12px 15px; margin: 10px 0; border-radius: 3px; }
        .bank-details p { margin: 3px 0; }
        .signature-section { margin-top: 40px; }
        .signature-line { border-bottom: 1px solid #333; width: 300px; margin: 30px 0 5px; }
        .terms-title { font-size: 13pt; font-weight: bold; color: #1a3a5c; text-align: center; margin: 30px 0 15px; border-top: 2px solid #1a3a5c; padding-top: 15px; }
        .terms-section { margin-bottom: 12px; }
        .terms-section h4 { font-size: 10pt; color: #1a3a5c; margin: 0 0 5px; }
        .terms-section p, .terms-section li { font-size: 9pt; margin: 3px 0; }
        .terms-section ol, .terms-section ul { padding-left: 20px; }
        .footer { margin-top: 30px; font-size: 8pt; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 10px; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    @php
        $client = $form->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $clientAddress = collect([$client->address, $client->city, $client->state, $client->zip])
            ->filter()->implode(', ');
    @endphp

    <div class="header">
        <h1>Costs Agreement</h1>
    </div>

    <div class="intro">
        <p>Thank you for your instructions to act in this matter.</p>
        <p>This Costs Agreement, together with the Terms and Conditions below, sets out the terms of our engagement to provide legal services to you, and constitutes our costs agreement and disclosure pursuant to the Legal Profession Uniform Law (VIC).</p>
        <p><strong>Please read this document carefully. It contains important information about the cost of our legal services and your rights regarding costs.</strong></p>
    </div>

    <table class="details-table">
        <tr>
            <td class="label">Date</td>
            <td class="value">{{ $form->form_date ? $form->form_date->format('j F Y') : now()->format('j F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Matter Reference</td>
            <td class="value">{{ $form->matter_reference ?? ($form->matter ? $form->matter->client_unique_matter_no : '') }}</td>
        </tr>
        <tr>
            <td class="label">Client</td>
            <td class="value">{{ $clientName }}{{ $clientAddress ? ' of ' . $clientAddress : '' }}</td>
        </tr>
        <tr>
            <td class="label">Firm Contact Details</td>
            <td class="value">
                {{ $form->firm_name }}<br>
                {{ $form->firm_address }}<br>
                Phone: {{ $form->firm_phone }}<br>
                Email: {{ $form->firm_email }}
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Scope of Work</div>
        <div class="scope-box">{!! nl2br(e($form->scope_of_work ?? '')) !!}</div>
        <p style="font-size: 9pt; color: #666; margin-top: 5px;">The work we will undertake is limited to these items and does not include any work not specifically referred to. If we agree to undertake additional work, we will confirm it in writing.</p>
    </div>

    <div class="section">
        <div class="section-title">Person Responsible</div>
        <p>{{ $form->person_responsible ?? '' }} will be responsible for the work. Please contact them if you have any concerns about our costs or your matter.</p>
        @if($form->person_responsible_email)
        <p>Email: {{ $form->person_responsible_email }}</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Our Fees, Disbursements, and Internal Expenses</div>
        @if($form->fee_type === 'fixed')
        <p><strong>Fixed Fees:</strong> Our fee is an agreed fixed fee of ${{ number_format($form->fixed_fee_amount, 2) }} plus GST.</p>
        @else
        <p><strong>Fees:</strong> Our fees will be calculated based on the time spent on your matter.</p>
        @endif
        <p><strong>Disbursements and Internal Expenses:</strong> During the course of your matter, it may be necessary to incur disbursements, which are fees, expenses, and charges such as court filing fees, bank charges, courier fees, barrister's fees, title searching and property enquiries, agency fees for law stationers, and process serving.</p>
        <p>Disbursements are payable as and when they fall due for payment. We will not incur any substantial expense without first obtaining your permission.</p>
    </div>

    <div class="section">
        <div class="section-title">Costs Estimate</div>
        @if($form->cost_estimate_breakdown)
        <div class="scope-box">{!! nl2br(e($form->cost_estimate_breakdown)) !!}</div>
        @else
        <table class="costs-table">
            <tr>
                <th>Description</th>
                <th style="width: 150px; text-align: right;">Amount</th>
            </tr>
            <tr>
                <td>Our fees (excl. GST)</td>
                <td class="amount">${{ number_format($form->estimated_legal_fees, 2) }}</td>
            </tr>
            <tr>
                <td>Disbursements</td>
                <td class="amount">${{ number_format($form->estimated_disbursements, 2) }}</td>
            </tr>
            <tr>
                <td>GST</td>
                <td class="amount">${{ number_format($form->gst_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Estimated total (incl. GST)</td>
                <td class="amount">${{ number_format($form->estimated_total, 2) }}</td>
            </tr>
        </table>
        @endif
        <p style="font-size: 9pt; color: #666;">This estimate is not binding on us and is based on our experience of similar matters. Our costs may exceed this estimate if circumstances change.</p>
    </div>

    @if($form->variables_affecting_costs)
    <div class="section">
        <div class="section-title">Variables That Might Affect Costs Estimate</div>
        <div class="scope-box">{!! nl2br(e($form->variables_affecting_costs)) !!}</div>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Expert Costs</div>
        <p>It may be necessary for us to engage the services of another lawyer, a barrister, or other experts on your behalf to provide specialist advice or services. We will consult you as to the terms of their engagement. You may be asked to enter into a costs agreement directly with them.</p>
    </div>

    <div class="section">
        <div class="section-title">Costs in Litigation Matters</div>
        <p>If litigation is required in your matter, the outcome may affect the costs payable or recoverable by you.</p>
        <p>If a court makes an order requiring another party to pay your costs of the proceedings, that order will not affect your liability to pay our fees, disbursements, and internal expenses under this agreement, but any amount recovered will reduce this amount.</p>
        <p>If a court makes an order requiring you to pay the legal costs of another party, you will likely have to pay these costs in addition to the costs payable to us under this agreement.</p>
    </div>

    <div class="section">
        <div class="section-title">Payment Arrangements</div>
        @if($form->retainer_amount > 0)
        <p>Please pay us the sum of ${{ number_format($form->retainer_amount, 2) }} which we will hold in trust for payment of our costs, disbursements, and internal expenses disclosed above.</p>
        @endif
        <p>We may request that you top up the retainer during the matter. Any remaining funds will be returned on completion of the matter or termination of this agreement.</p>
        <p>Our trust account details are provided below:</p>
        <div class="bank-details">
            <p><strong>Name of account:</strong> {{ $form->trust_account_name }}</p>
            <p><strong>Institution:</strong> {{ $form->trust_account_institution }}</p>
            <p><strong>BSB:</strong> {{ $form->trust_account_bsb }}</p>
            <p><strong>Account number:</strong> {{ $form->trust_account_number }}</p>
            <p><strong>Reference:</strong> {{ $form->payment_reference ?? $form->matter_reference ?? '' }}</p>
        </div>
        <p style="font-size: 9pt; color: #c00;">To provide maximum protection against fraud we recommend you always telephone our office to confirm payment details before making a transfer.</p>
    </div>

    <div class="section">
        <div class="section-title">Next Steps</div>
        <p>Please:</p>
        <ul>
            <li>Read this document. If you are happy to proceed, sign and return a copy. If you require any amendments, please let us know.</li>
            <li>Make a payment on account of costs as set out in the Payment Arrangements clause above.</li>
            <li>Complete and return any other documents requested in our initial letter.</li>
        </ul>
    </div>

    <div class="signature-section">
        <div class="section-title">Acceptance</div>
        <p>I have read and understood this costs agreement, including the Terms and Conditions below.</p>
        <p><strong>SIGNED BY</strong></p>
        <div class="signature-line"></div>
        <p>{{ $clientName }}</p>
        <p>Date: ______________________</p>
    </div>

    <div style="page-break-before: always;"></div>

    <div class="terms-title">Terms and Conditions</div>

    <div class="terms-section">
        <h4>1. Your Rights</h4>
        <ol type="a">
            <li><strong>Generally</strong>
                <ol type="i">
                    <li>You may seek independent legal advice before agreeing to the costs agreement proposed.</li>
                    <li>You may negotiate the billing method used, for example, by reference to timing or task.</li>
                    <li>You may negotiate the terms of this costs agreement.</li>
                    <li>We are required to notify you, as soon as is reasonably practicable, of any significant change to any matter affecting costs.</li>
                    <li>You are entitled to accept or reject any offer we make for an interstate costs law to apply to your matter.</li>
                    <li>The law of Victoria applies to legal costs in relation to this matter.</li>
                    <li>Nothing in these Terms and Conditions affects your rights under the Australian Consumer Law.</li>
                </ol>
            </li>
            <li><strong>Your right to a bill of costs</strong>
                <ol type="i">
                    <li>You are entitled to receive a bill of costs for the legal services provided by us to you.</li>
                    <li>If a lump sum bill is provided by us, you have the right to ask for an itemised bill within 30 days.</li>
                </ol>
            </li>
            <li><strong>Your right to fair and reasonable costs</strong> - The Legal Profession Uniform Law (VIC) gives you the right to have all or any part of our legal costs assessed by a costs assessor.</li>
            <li><strong>Your right to written progress reports</strong> - You are entitled, on reasonable request, to written reports on the progress of the matter and the legal costs incurred.</li>
        </ol>
    </div>

    <div class="terms-section">
        <h4>2. Billing and Payment</h4>
        <ol type="a">
            <li>Each month, or on the completion of specific tasks, we will send you either a lump sum bill or an itemised bill.</li>
            <li>You consent to us sending our accounts to you electronically at the email address you have provided.</li>
            <li>Payment may be made using credit card, BPAY, electronic funds transfer (EFT), instalment plans by direct debit, or pay by the month.</li>
            <li>Where applicable, GST is payable on our professional fees and expenses.</li>
        </ol>
    </div>

    <div class="terms-section">
        <h4>3. Interest Charges</h4>
        <p>Interest will be charged on unpaid legal costs outstanding for more than 30 days at the Cash Rate Target fixed by the Reserve Bank of Australia plus 2%.</p>
    </div>

    <div class="terms-section">
        <h4>4. Trust Money</h4>
        <p>You authorise us to receive into our trust account any settlement amount or money received in furtherance of your work, and to pay our professional fees in accordance with the Legal Profession Uniform Law.</p>
    </div>

    <div class="terms-section">
        <h4>5. Disputes Concerning Costs and Failure to Pay</h4>
        <p>If you dispute our professional charges or disbursements, you agree to advise us of your concerns as soon as possible. You have the right to seek the assistance of the Victorian Legal Services Commissioner or have the costs assessed by the Supreme Court of Victoria.</p>
    </div>

    <div class="terms-section">
        <h4>6. Lien</h4>
        <p>Legal ownership in all documents, records, papers, titles, funds, property, and any other material created or obtained by us belongs to us until all outstanding fees have been paid.</p>
    </div>

    <div class="terms-section">
        <h4>7. Termination of This Agreement</h4>
        <ol type="a">
            <li>We may cease to act if you fail to pay our bills, fail to provide adequate instructions, refuse to accept our advice, or for any other reason that compromises our ability to perform the work.</li>
            <li>We will give you at least 14 days notice of our intention to terminate.</li>
            <li>You may terminate this agreement in writing at any time. You will pay our fees incurred up to the time of termination.</li>
        </ol>
    </div>

    <div class="terms-section">
        <h4>8. Electronic Communication</h4>
        <p>You consent to us communicating electronically with or for you. There are risks in using email and you accept those risks including interception by third parties, non-receipt, or delayed receipt of messages.</p>
    </div>

    <div class="terms-section">
        <h4>9. Document Ownership, Retention, and Destruction</h4>
        <p>On completion of your matter, we will retain your documents for 7 years. You authorise us to destroy them after this time.</p>
    </div>

    <div class="terms-section">
        <h4>10. Privacy</h4>
        <p>Your personal information will only be used for the purposes for which it is collected or in accordance with the Privacy Act 1988 (Cth). We will hold your personal information in strict confidence.</p>
    </div>

    <div class="terms-section">
        <h4>11. Acceptance</h4>
        <p>Before acceptance of this offer you are entitled to negotiate these terms. If you do not return the signed agreement but instruct us to commence work, that will be taken to be an acceptance of this offer.</p>
    </div>

    <div class="footer">
        {{ $form->firm_name }} &bull; {{ $form->firm_address }} &bull; {{ $form->firm_phone }}
    </div>
</body>
</html>
