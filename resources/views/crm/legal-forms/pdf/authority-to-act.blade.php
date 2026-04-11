<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Authority to Act</title>
    <style>
        @page { margin: 3cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11pt; color: #333; line-height: 1.8; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { font-size: 20pt; color: #1a3a5c; margin: 0; letter-spacing: 1px; text-transform: uppercase; border-bottom: 3px solid #1a3a5c; padding-bottom: 15px; display: inline-block; }
        .firm-details { text-align: center; margin-bottom: 30px; font-size: 10pt; color: #666; }
        .content { margin: 30px 0; }
        .content p { margin-bottom: 15px; text-align: justify; }
        .scope-list { margin: 15px 0 25px 20px; }
        .scope-list li { margin-bottom: 10px; text-align: justify; }
        .closing { margin-top: 20px; font-style: italic; }
        .signature-section { margin-top: 60px; }
        .signature-block { margin-top: 40px; }
        .signature-line { border-bottom: 1px solid #333; width: 300px; margin-bottom: 5px; height: 40px; }
        .signature-name { font-weight: bold; font-size: 12pt; }
        .signature-date { margin-top: 15px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    @php
        $client = $form->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
    @endphp

    <div class="header">
        <h1>Authority to Act</h1>
    </div>

    <div class="firm-details">
        {{ $form->firm_name }} &bull; {{ $form->firm_address }}<br>
        Phone: {{ $form->firm_phone }} &bull; Email: {{ $form->firm_email }}
    </div>

    <div class="content">
        <p>I, <strong>{{ $clientName }}</strong>, authorise <strong>{{ $form->firm_name }}</strong> to act on my behalf in relation to:</p>

        @if($form->authority_scope)
        <p>{!! nl2br(e($form->authority_scope)) !!}</p>
        @elseif($form->scope_of_work)
        <p>{!! nl2br(e($form->scope_of_work)) !!}</p>
        @else
        <p>______________________________________________________________________</p>
        <p>______________________________________________________________________</p>
        @endif

        <p>This authority includes, but is not limited to:</p>
        <ul class="scope-list">
            <li>Advising me in relation to all matters and documents associated with the above;</li>
            <li>Communicating and corresponding with all relevant parties on my behalf;</li>
            <li>Requesting, reviewing, and obtaining all documents and information relevant to this matter;</li>
            <li>Taking all necessary steps to protect my interests in connection with this matter.</li>
        </ul>

        <p class="closing">I give this authority voluntarily and understand it remains in place unless I cancel it in writing.</p>
    </div>

    <div class="signature-section">
        <div class="signature-block">
            <p><strong>Signed:</strong></p>
            <div class="signature-line"></div>
            <p class="signature-name">{{ $clientName }}</p>
            <p class="signature-date">Date: {{ $form->form_date ? $form->form_date->format('d/m/Y') : '____/____/________' }}</p>
        </div>
    </div>

    <div class="footer">
        {{ $form->firm_name }} &bull; {{ $form->firm_address }} &bull; {{ $form->firm_phone }}
    </div>
</body>
</html>
