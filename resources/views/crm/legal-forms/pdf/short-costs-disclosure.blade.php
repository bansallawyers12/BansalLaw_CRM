<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Short Costs Disclosure</title>
    <style>
        @page { margin: 2cm 2.5cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #333; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1a3a5c; padding-bottom: 15px; }
        .header h1 { font-size: 14pt; color: #1a3a5c; margin: 0 0 5px; }
        .header h2 { font-size: 11pt; color: #555; font-weight: normal; margin: 0; }
        .form-notice { background: #f0f4f8; border: 1px solid #d0d8e0; padding: 10px 15px; margin-bottom: 20px; font-size: 9pt; color: #555; border-radius: 3px; }
        .section { margin-bottom: 15px; }
        .section-title { font-size: 11pt; font-weight: bold; color: #1a3a5c; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 10px; }
        table.form-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.form-table td { padding: 5px 8px; vertical-align: top; border: 1px solid #ddd; }
        table.form-table td.label { width: 35%; background: #f7f9fb; font-weight: bold; color: #444; }
        table.form-table td.value { width: 65%; }
        .scope-box { border: 1px solid #ddd; padding: 10px 15px; background: #fafafa; min-height: 60px; white-space: pre-line; }
        .costs-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .costs-table td, .costs-table th { padding: 6px 10px; border: 1px solid #ddd; }
        .costs-table th { background: #f0f4f8; text-align: left; font-weight: bold; color: #1a3a5c; }
        .costs-table .amount { text-align: right; font-weight: bold; }
        .total-row td { background: #1a3a5c; color: #fff; font-weight: bold; }
        .rights-box { background: #f7f9fb; border: 1px solid #d0d8e0; padding: 12px 15px; font-size: 9pt; border-radius: 3px; }
        .rights-box ul { margin: 5px 0; padding-left: 20px; }
        .rights-box li { margin-bottom: 3px; }
        .footer { margin-top: 30px; font-size: 8pt; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Schedule 1 Form 1</h1>
        <h2>Standard Costs Disclosure Form for Clients</h2>
    </div>

    <div class="form-notice">
        The standard costs disclosure Form 1 can be used when your professional fee is not likely to be more than $3,000 (before adding GST and disbursements).
    </div>

    <div class="section">
        <div class="section-title">Date Provided to Client</div>
        <p>{{ $form->form_date ? $form->form_date->format('d/m/Y') : now()->format('d/m/Y') }}</p>
    </div>

    <div class="section">
        <div class="section-title">Law Practice Details</div>
        <table class="form-table">
            <tr><td class="label">Name</td><td class="value">{{ $form->firm_name }}</td></tr>
            <tr><td class="label">Contact</td><td class="value">{{ $form->firm_contact ?? $form->person_responsible }}</td></tr>
            <tr><td class="label">Address</td><td class="value">{{ $form->firm_address }}</td></tr>
            <tr><td class="label">Phone</td><td class="value">{{ $form->firm_phone }}</td></tr>
            <tr><td class="label">Mobile</td><td class="value">{{ $form->firm_mobile ?? '' }}</td></tr>
            <tr><td class="label">State/Territory</td><td class="value">{{ $form->firm_state }}</td></tr>
            <tr><td class="label">Postcode</td><td class="value">{{ $form->firm_postcode }}</td></tr>
            <tr><td class="label">Email</td><td class="value">{{ $form->firm_email }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Client Details</div>
        @php
            $client = $form->client;
            $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
            $clientAddress = trim(($client->address ?? '') . ', ' . ($client->city ?? '') . ' ' . ($client->state ?? '') . ' ' . ($client->zip ?? ''));
            $clientAddress = trim($clientAddress, ', ');
        @endphp
        <table class="form-table">
            <tr><td class="label">Name</td><td class="value">{{ $clientName }}</td></tr>
            <tr><td class="label">Phone</td><td class="value">{{ $client->phone ?? '' }}</td></tr>
            <tr><td class="label">Address</td><td class="value">{{ $clientAddress }}</td></tr>
            <tr><td class="label">Email</td><td class="value">{{ $client->email ?? '' }}</td></tr>
            <tr><td class="label">State/Territory</td><td class="value">{{ $client->state ?? '' }}</td></tr>
            <tr><td class="label">Postcode</td><td class="value">{{ $client->zip ?? '' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">What We Will Do for You</div>
        <div class="scope-box">{!! nl2br(e($form->scope_of_work ?? '')) !!}</div>
    </div>

    <div class="section">
        <div class="section-title">How Much We Estimate You Will Need to Pay</div>
        <table class="costs-table">
            <tr>
                <th>Description</th>
                <th style="width: 150px; text-align: right;">Amount</th>
            </tr>
            <tr>
                <td>Estimated total cost of our legal services (excl. GST)</td>
                <td class="amount">${{ number_format($form->estimated_legal_fees, 2) }}</td>
            </tr>
            <tr>
                <td>Estimated amount for disbursements (excl. GST)</td>
                <td class="amount">${{ number_format($form->estimated_disbursements, 2) }}</td>
            </tr>
            <tr>
                <td>Estimated total cost of barrister or other law practice (excl. GST)</td>
                <td class="amount">${{ number_format($form->estimated_barrister_fees, 2) }}</td>
            </tr>
            <tr>
                <td>GST</td>
                <td class="amount">${{ number_format($form->gst_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Estimated full amount you will need to pay (incl. GST)</td>
                <td class="amount">${{ number_format($form->estimated_total, 2) }}</td>
            </tr>
        </table>
        <p style="font-size: 9pt; color: #666;">This is an estimate only. We will inform you if anything happens that significantly changes this estimate. If our professional fee is likely to be more than $3,000 (before GST and disbursements are added) we will provide you with a full disclosure of costs in writing.</p>
    </div>

    <div class="section">
        <div class="section-title">Your Rights</div>
        <div class="rights-box">
            <p>Your rights include to:</p>
            <ul>
                <li>Ask for an explanation of this form</li>
                <li>Negotiate a costs agreement</li>
                <li>Negotiate the billing method (e.g. timing or task)</li>
                <li>Request a written progress report of costs incurred</li>
                <li>Receive a written bill for work done</li>
                <li>Request an itemised bill</li>
                <li>Contact your local regulatory authority</li>
            </ul>
        </div>
    </div>

    <div class="footer">
        {{ $form->firm_name }} &bull; {{ $form->firm_address }} &bull; {{ $form->firm_phone }}
    </div>
</body>
</html>
