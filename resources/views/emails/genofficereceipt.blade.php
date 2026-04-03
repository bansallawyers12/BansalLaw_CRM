<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Helvetica Neue', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            line-height: 1.4;
        }
        .invoice_table {
            max-width: 800px;
            margin: 0 auto;
            padding: 8px;
        }
        .header-section {
            border-bottom: 3px solid #3abaf4;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .company-info {
            font-size: 13px;
            line-height: 1.6;
            color: #1f2937;
            margin-top: 8px;
        }
        .company-name {
            font-size: 15px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }
        .document-title {
            font-size: 26px;
            font-weight: 700;
            color: #3abaf4;
            margin: 0 0 10px 0;
            letter-spacing: -0.5px;
        }
        .document-info {
            font-size: 13px;
            line-height: 1.7;
            color: #1f2937;
        }
        .document-info b {
            color: #111827;
            font-weight: 600;
        }
        .bill-to-section {
            background: #f9f9f9;
            padding: 10px 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .bill-to-label {
            font-size: 13px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        .bill-to-content {
            font-size: 13px;
            line-height: 1.5;
            color: #1f2937;
        }
        .matter-info {
            font-size: 13px;
            color: #1f2937;
            margin: 8px 0;
        }
        .ledger-table-wrapper {
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ledger-table thead {
            background: #3abaf4;
        }
        .ledger-table thead th {
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #ffffff !important;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .ledger-table tbody tr {
            border-bottom: 1px solid #eee;
        }
        .ledger-table tbody td {
            padding: 9px 8px;
            font-size: 12px;
            color: #1f2937;
        }
        .ledger-table thead th:last-child,
        .ledger-table tbody td:last-child {
            text-align: right;
        }
        .total-section {
            margin: 12px 0;
            padding: 10px 12px;
            background: #f9f9f9;
            border-radius: 4px;
            text-align: right;
            font-size: 15px;
        }
        .total-label {
            font-weight: 700;
            color: #333;
        }
        .total-value {
            font-weight: 700;
            color: #3abaf4;
            font-size: 17px;
        }
        .acknowledgement {
            background: #f0f8ff;
            border-left: 4px solid #3abaf4;
            padding: 10px 12px;
            margin-top: 12px;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.6;
        }
        .acknowledgement p {
            margin: 0;
        }
        .acknowledgement strong {
            color: #111827;
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('img/logo.png');
        $logoData = '';
        if(file_exists($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }
    @endphp
    
    <div class="invoice_table">
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr class="header-section">
                <td style="text-align: left; width: 50%; vertical-align: top;">
                    @if($logoData)
                        <img width="85" style="height:auto;display:block;margin-bottom:10px;" src="{{$logoData}}" alt="Logo"/>
                    @else
                        <div style="width:85px;height:55px;background:#3abaf4;display:block;margin-bottom:10px;"></div>
                    @endif
                    <div class="company-name">BANSAL IMMIGRATION</div>
                    <div class="company-info">
                        Level 8, 278 Collins Street<br/>
                        Melbourne VIC 3000<br/>
                        E-mail: invoice@bansalimmigration.com.au<br/>
                        Phone: 03 96021330
                    </div>
                </td>
                <td style="text-align: right; width: 50%; vertical-align: top;">
                    <h1 class="document-title">Office Receipt</h1>
                    <div class="document-info">
                        <b>ABN</b> 70 958 120 428<br/>
                        <b>Receipt Date:</b> {{@$record_get->trans_date ? $record_get->trans_date : date('d/m/Y')}}<br/>
                        <b>Receipt No:</b> {{@$record_get->trans_no}}
                    </div>
                </td>
            </tr>
        </table>

        <div class="bill-to-section">
            <div class="bill-to-label">Bill To:</div>
            <div class="bill-to-content">
                <strong>{{@$clientname->first_name}} {{@$clientname->last_name}}</strong><br/>
                @if(!empty($clientname->address))
                    {{$clientname->address}}@if(!empty($clientname->city) || !empty($clientname->state) || !empty($clientname->zip) || !empty($clientname->country)), @endif
                @endif
                @php
                    $addressParts = array_filter([
                        $clientname->city ?? '',
                        $clientname->state ?? '',
                        $clientname->zip ?? '',
                        $clientname->country ?? ''
                    ]);
                @endphp
                @if(!empty($addressParts))
                    {{ implode(' ', $addressParts) }}
                @endif
            </div>
        </div>

        <div class="matter-info">
            <strong>Matter:</strong> {{ $client_matter_display ?? $client_matter_no ?? 'N/A' }}
        </div>

        <div class="ledger-table-wrapper">
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>Trans. Date</th>
                        <th>Entry Date</th>
                        <th>Description</th>
                        <th>Payment Method</th>
                        <th>Received</th>
                    </tr>
                </thead>
                <tbody>
                    @if($record_get)
                        <tr>
                            <td>{{@$record_get->trans_date}}</td>
                            <td>{{@$record_get->entry_date}}</td>
                            <td>{{@$record_get->description}}@if(!empty($record_get->invoice_no)) ({{@$record_get->invoice_no}})@endif</td>
                            <td>{{@$record_get->payment_method}}</td>
                            <td style="text-align:right;">${{number_format($record_get->deposit_amount, 2)}}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="total-section">
            <span class="total-label">Total Amount Received: </span>
            <span class="total-value">${{number_format($record_get->deposit_amount ?? 0, 2)}}</span>
        </div>

        <div class="acknowledgement">
            <p><strong>Receipt Acknowledgement:</strong> This receipt confirms payment received for matter <strong>{{ $client_matter_display ?? $client_matter_no ?? 'N/A' }}</strong>.</p>
        </div>
    </div>
</body>
</html>

