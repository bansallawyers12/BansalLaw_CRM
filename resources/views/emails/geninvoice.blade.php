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
        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #333;
            margin: 10px 0 8px 0;
            padding-bottom: 6px;
            border-bottom: 2px solid #e0e0e0;
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
        .ledger-table tbody td:last-child,
        .ledger-table thead th:nth-child(3),
        .ledger-table tbody td:nth-child(3) {
            text-align: right;
        }
        .totals-section {
            margin: 12px 0;
            padding: 10px 12px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .totals-row {
            display: table;
            width: 100%;
            padding: 3px 0;
            font-size: 13px;
        }
        .totals-label {
            display: table-cell;
            text-align: right;
            padding-right: 15px;
            color: #1a1a1a;
            font-weight: 600;
        }
        .totals-value {
            display: table-cell;
            text-align: right;
            width: 150px;
            color: #333;
            font-weight: 700;
        }
        .payment-instructions {
            padding: 10px 12px;
            background: #f0f8ff;
            border-left: 4px solid #3abaf4;
            margin-top: 12px;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.6;
        }
        .payment-instructions p {
            margin: 5px 0;
        }
        .bank-details {
            background: #fff;
            padding: 8px 10px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 12px;
            line-height: 1.7;
        }
        .payment-method-highlight {
            color: #333;
            font-weight: 700;
            font-size: 13px;
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
                    <h1 class="document-title">Tax Invoice</h1>
                    <div class="document-info">
                        <b>ABN</b> 70 958 120 428<br/>
                        <b>Invoice Date:</b> 
                        @php
                            $invoiceDate = 'N/A';
                            try {
                                if (!empty($record_get[0]->trans_date)) {
                                    $rawDate = $record_get[0]->trans_date;
                                    // Check if it's already a Carbon instance
                                    if ($rawDate instanceof \Carbon\Carbon) {
                                        $invoiceDate = $rawDate->format('d/m/Y');
                                    } else {
                                        // Clean and trim the date string
                                        $cleanDate = trim(str_replace(["\r", "\n", "\t"], '', $rawDate));
                                        if (!empty($cleanDate)) {
                                            // Try multiple date formats
                                            $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'];
                                            $parsed = false;
                                            
                                            foreach ($formats as $format) {
                                                try {
                                                    $date = \Carbon\Carbon::createFromFormat($format, $cleanDate);
                                                    if ($date) {
                                                        $invoiceDate = $date->format('d/m/Y');
                                                        $parsed = true;
                                                        break;
                                                    }
                                                } catch (\Exception $e) {
                                                    continue;
                                                }
                                            }
                                            
                                            // If no format worked, try Carbon::parse as last resort
                                            if (!$parsed) {
                                                try {
                                                    $invoiceDate = \Carbon\Carbon::parse($cleanDate)->format('d/m/Y');
                                                } catch (\Exception $e) {
                                                    $invoiceDate = $cleanDate; // Just show the raw date
                                                }
                                            }
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                $invoiceDate = 'N/A';
                            }
                        @endphp
                        {{ $invoiceDate }}<br/>
                        <b>Due Date:</b> 
                        @php
                            $dueDate = 'N/A';
                            try {
                                if ($invoiceDate !== 'N/A' && !empty($record_get[0]->trans_date)) {
                                    $rawDate = $record_get[0]->trans_date;
                                    // Check if it's already a Carbon instance
                                    if ($rawDate instanceof \Carbon\Carbon) {
                                        $dueDate = $rawDate->copy()->addDays(15)->format('d/m/Y');
                                    } else {
                                        // Clean and trim the date string
                                        $cleanDate = trim(str_replace(["\r", "\n", "\t"], '', $rawDate));
                                        
                                        if (!empty($cleanDate)) {
                                            // Try multiple date formats
                                            $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'];
                                            $parsed = false;
                                            
                                            foreach ($formats as $format) {
                                                try {
                                                    $date = \Carbon\Carbon::createFromFormat($format, $cleanDate);
                                                    if ($date) {
                                                        $dueDate = $date->addDays(15)->format('d/m/Y');
                                                        $parsed = true;
                                                        break;
                                                    }
                                                } catch (\Exception $e) {
                                                    continue;
                                                }
                                            }
                                            
                                            // If no format worked, try Carbon::parse as last resort
                                            if (!$parsed) {
                                                try {
                                                    $dueDate = \Carbon\Carbon::parse($cleanDate)->addDays(15)->format('d/m/Y');
                                                } catch (\Exception $e) {
                                                    $dueDate = 'N/A';
                                                }
                                            }
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                $dueDate = 'N/A';
                            }
                        @endphp
                        {{ $dueDate }}<br/>
                        <b>Invoice No:</b> {{$record_get[0]->invoice_no ?? 'N/A'}}
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

        @if($record_get_Professional_Fee_cnt > 0)
            <h4 class="section-title">Professional Fee</h4>
            <div class="ledger-table-wrapper">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Unit Price</th>
                            <th>GST Incl.</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($record_get_Professional_Fee))
                            @foreach($record_get_Professional_Fee as $fee)
                                <tr>
                                    <td>{{@$fee->trans_date}}</td>
                                    <td>{{@$fee->description}}</td>
                                    <td style="text-align:right;">
                                        @php
                                            $amount = 0;
                                            try {
                                                $amount = floatval($fee->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $amount = 0;
                                            }
                                            
                                            $unitPrice = 0;
                                            try {
                                                if (@$fee->gst_included === 'Yes') {
                                                    $unitPrice = $amount - ($amount / 11);
                                                } else {
                                                    $unitPrice = $amount;
                                                }
                                            } catch (\Exception $e) {
                                                $unitPrice = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($unitPrice) || !is_finite($unitPrice)) {
                                                $unitPrice = 0;
                                            }
                                            
                                            $formattedUnitPrice = '0.00';
                                            try {
                                                $formattedUnitPrice = number_format((float)$unitPrice, 2);
                                            } catch (\Exception $e) {
                                                $formattedUnitPrice = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedUnitPrice }}
                                    </td>
                                    <td>
                                        @php
                                            $gstIncluded = 'N/A';
                                            try {
                                                if (isset($fee->gst_included) && $fee->gst_included !== null && $fee->gst_included !== '') {
                                                    $gstIncluded = (string)$fee->gst_included;
                                                }
                                            } catch (\Exception $e) {
                                                $gstIncluded = 'N/A';
                                            }
                                        @endphp
                                        {{ $gstIncluded }}
                                    </td>
                                    <td style="text-align:right;">
                                        @php
                                            $totalAmount = 0;
                                            try {
                                                $totalAmount = floatval($fee->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $totalAmount = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($totalAmount) || !is_finite($totalAmount)) {
                                                $totalAmount = 0;
                                            }
                                            
                                            $formattedTotal = '0.00';
                                            try {
                                                $formattedTotal = number_format((float)$totalAmount, 2);
                                            } catch (\Exception $e) {
                                                $formattedTotal = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedTotal }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        @endif

        @if($record_get_Department_Charges_cnt > 0)
            <h4 class="section-title">Department Charges</h4>
            <div class="ledger-table-wrapper">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Unit Price</th>
                            <th>GST Incl.</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($record_get_Department_Charges))
                            @foreach($record_get_Department_Charges as $charge)
                                <tr>
                                    <td>{{@$charge->trans_date}}</td>
                                    <td>{{@$charge->description}}</td>
                                    <td style="text-align:right;">
                                        @php
                                            $amount = 0;
                                            try {
                                                $amount = floatval($charge->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $amount = 0;
                                            }
                                            
                                            $unitPrice = 0;
                                            try {
                                                if (@$charge->gst_included === 'Yes') {
                                                    $unitPrice = $amount - ($amount / 11);
                                                } else {
                                                    $unitPrice = $amount;
                                                }
                                            } catch (\Exception $e) {
                                                $unitPrice = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($unitPrice) || !is_finite($unitPrice)) {
                                                $unitPrice = 0;
                                            }
                                            
                                            $formattedUnitPrice = '0.00';
                                            try {
                                                $formattedUnitPrice = number_format((float)$unitPrice, 2);
                                            } catch (\Exception $e) {
                                                $formattedUnitPrice = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedUnitPrice }}
                                    </td>
                                    <td>
                                        @php
                                            $gstIncluded = 'N/A';
                                            try {
                                                if (isset($charge->gst_included) && $charge->gst_included !== null && $charge->gst_included !== '') {
                                                    $gstIncluded = (string)$charge->gst_included;
                                                }
                                            } catch (\Exception $e) {
                                                $gstIncluded = 'N/A';
                                            }
                                        @endphp
                                        {{ $gstIncluded }}
                                    </td>
                                    <td style="text-align:right;">
                                        @php
                                            $totalAmount = 0;
                                            try {
                                                $totalAmount = floatval($charge->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $totalAmount = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($totalAmount) || !is_finite($totalAmount)) {
                                                $totalAmount = 0;
                                            }
                                            
                                            $formattedTotal = '0.00';
                                            try {
                                                $formattedTotal = number_format((float)$totalAmount, 2);
                                            } catch (\Exception $e) {
                                                $formattedTotal = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedTotal }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        @endif

        @if($record_get_Surcharge_cnt > 0)
            <h4 class="section-title">Surcharge</h4>
            <div class="ledger-table-wrapper">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Unit Price</th>
                            <th>GST Incl.</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($record_get_Surcharge))
                            @foreach($record_get_Surcharge as $surcharge)
                                <tr>
                                    <td>{{@$surcharge->trans_date}}</td>
                                    <td>{{@$surcharge->description}}</td>
                                    <td style="text-align:right;">
                                        @php
                                            $amount = 0;
                                            try {
                                                $amount = floatval($surcharge->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $amount = 0;
                                            }
                                            
                                            $unitPrice = 0;
                                            try {
                                                if (@$surcharge->gst_included === 'Yes') {
                                                    $unitPrice = $amount - ($amount / 11);
                                                } else {
                                                    $unitPrice = $amount;
                                                }
                                            } catch (\Exception $e) {
                                                $unitPrice = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($unitPrice) || !is_finite($unitPrice)) {
                                                $unitPrice = 0;
                                            }
                                            
                                            $formattedUnitPrice = '0.00';
                                            try {
                                                $formattedUnitPrice = number_format((float)$unitPrice, 2);
                                            } catch (\Exception $e) {
                                                $formattedUnitPrice = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedUnitPrice }}
                                    </td>
                                    <td>
                                        @php
                                            $gstIncluded = 'N/A';
                                            try {
                                                if (isset($surcharge->gst_included) && $surcharge->gst_included !== null && $surcharge->gst_included !== '') {
                                                    $gstIncluded = (string)$surcharge->gst_included;
                                                }
                                            } catch (\Exception $e) {
                                                $gstIncluded = 'N/A';
                                            }
                                        @endphp
                                        {{ $gstIncluded }}
                                    </td>
                                    <td style="text-align:right;">
                                        @php
                                            $totalAmount = 0;
                                            try {
                                                $totalAmount = floatval($surcharge->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $totalAmount = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($totalAmount) || !is_finite($totalAmount)) {
                                                $totalAmount = 0;
                                            }
                                            
                                            $formattedTotal = '0.00';
                                            try {
                                                $formattedTotal = number_format((float)$totalAmount, 2);
                                            } catch (\Exception $e) {
                                                $formattedTotal = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedTotal }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        @endif

        @if($record_get_Disbursements_cnt > 0)
            <h4 class="section-title">Disbursements</h4>
            <div class="ledger-table-wrapper">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Unit Price</th>
                            <th>GST Incl.</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($record_get_Disbursements))
                            @foreach($record_get_Disbursements as $disbursement)
                                <tr>
                                    <td>{{@$disbursement->trans_date}}</td>
                                    <td>{{@$disbursement->description}}</td>
                                    <td style="text-align:right;">
                                        @php
                                            $amount = 0;
                                            try {
                                                $amount = floatval($disbursement->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $amount = 0;
                                            }
                                            
                                            $unitPrice = 0;
                                            try {
                                                if (@$disbursement->gst_included === 'Yes') {
                                                    $unitPrice = $amount - ($amount / 11);
                                                } else {
                                                    $unitPrice = $amount;
                                                }
                                            } catch (\Exception $e) {
                                                $unitPrice = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($unitPrice) || !is_finite($unitPrice)) {
                                                $unitPrice = 0;
                                            }
                                            
                                            $formattedUnitPrice = '0.00';
                                            try {
                                                $formattedUnitPrice = number_format((float)$unitPrice, 2);
                                            } catch (\Exception $e) {
                                                $formattedUnitPrice = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedUnitPrice }}
                                    </td>
                                    <td>
                                        @php
                                            $gstIncluded = 'N/A';
                                            try {
                                                if (isset($disbursement->gst_included) && $disbursement->gst_included !== null && $disbursement->gst_included !== '') {
                                                    $gstIncluded = (string)$disbursement->gst_included;
                                                }
                                            } catch (\Exception $e) {
                                                $gstIncluded = 'N/A';
                                            }
                                        @endphp
                                        {{ $gstIncluded }}
                                    </td>
                                    <td style="text-align:right;">
                                        @php
                                            $totalAmount = 0;
                                            try {
                                                $totalAmount = floatval($disbursement->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $totalAmount = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($totalAmount) || !is_finite($totalAmount)) {
                                                $totalAmount = 0;
                                            }
                                            
                                            $formattedTotal = '0.00';
                                            try {
                                                $formattedTotal = number_format((float)$totalAmount, 2);
                                            } catch (\Exception $e) {
                                                $formattedTotal = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedTotal }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        @endif

        @if($record_get_Other_Cost_cnt > 0)
            <h4 class="section-title">Other Cost</h4>
            <div class="ledger-table-wrapper">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Unit Price</th>
                            <th>GST Incl.</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($record_get_Other_Cost))
                            @foreach($record_get_Other_Cost as $cost)
                                <tr>
                                    <td>{{@$cost->trans_date}}</td>
                                    <td>{{@$cost->description}}</td>
                                    <td style="text-align:right;">
                                        @php
                                            $amount = 0;
                                            try {
                                                $amount = floatval($cost->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $amount = 0;
                                            }
                                            
                                            $unitPrice = 0;
                                            try {
                                                if (@$cost->gst_included === 'Yes') {
                                                    $unitPrice = $amount - ($amount / 11);
                                                } else {
                                                    $unitPrice = $amount;
                                                }
                                            } catch (\Exception $e) {
                                                $unitPrice = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($unitPrice) || !is_finite($unitPrice)) {
                                                $unitPrice = 0;
                                            }
                                            
                                            $formattedUnitPrice = '0.00';
                                            try {
                                                $formattedUnitPrice = number_format((float)$unitPrice, 2);
                                            } catch (\Exception $e) {
                                                $formattedUnitPrice = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedUnitPrice }}
                                    </td>
                                    <td>
                                        @php
                                            $gstIncluded = 'N/A';
                                            try {
                                                if (isset($cost->gst_included) && $cost->gst_included !== null && $cost->gst_included !== '') {
                                                    $gstIncluded = (string)$cost->gst_included;
                                                }
                                            } catch (\Exception $e) {
                                                $gstIncluded = 'N/A';
                                            }
                                        @endphp
                                        {{ $gstIncluded }}
                                    </td>
                                    <td style="text-align:right;">
                                        @php
                                            $totalAmount = 0;
                                            try {
                                                $totalAmount = floatval($cost->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $totalAmount = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($totalAmount) || !is_finite($totalAmount)) {
                                                $totalAmount = 0;
                                            }
                                            
                                            $formattedTotal = '0.00';
                                            try {
                                                $formattedTotal = number_format((float)$totalAmount, 2);
                                            } catch (\Exception $e) {
                                                $formattedTotal = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedTotal }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        @endif

        @if($record_get_Discount_cnt > 0)
            <h4 class="section-title">Discount</h4>
            <div class="ledger-table-wrapper">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Unit Price</th>
                            <th>GST Incl.</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(!empty($record_get_Discount))
                            @foreach($record_get_Discount as $discount)
                                <tr>
                                    <td>{{@$discount->trans_date}}</td>
                                    <td>{{@$discount->description}}</td>
                                    <td style="text-align:right;">
                                        @php
                                            $amount = 0;
                                            try {
                                                $amount = floatval($discount->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $amount = 0;
                                            }
                                            
                                            $unitPrice = 0;
                                            try {
                                                if (@$discount->gst_included === 'Yes') {
                                                    $unitPrice = $amount - ($amount / 11);
                                                } else {
                                                    $unitPrice = $amount;
                                                }
                                            } catch (\Exception $e) {
                                                $unitPrice = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($unitPrice) || !is_finite($unitPrice)) {
                                                $unitPrice = 0;
                                            }
                                            
                                            $formattedUnitPrice = '0.00';
                                            try {
                                                $formattedUnitPrice = number_format((float)$unitPrice, 2);
                                            } catch (\Exception $e) {
                                                $formattedUnitPrice = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedUnitPrice }}
                                    </td>
                                    <td>
                                        @php
                                            $gstIncluded = 'N/A';
                                            try {
                                                if (isset($discount->gst_included) && $discount->gst_included !== null && $discount->gst_included !== '') {
                                                    $gstIncluded = (string)$discount->gst_included;
                                                }
                                            } catch (\Exception $e) {
                                                $gstIncluded = 'N/A';
                                            }
                                        @endphp
                                        {{ $gstIncluded }}
                                    </td>
                                    <td style="text-align:right;">
                                        @php
                                            $totalAmount = 0;
                                            try {
                                                $totalAmount = floatval($discount->withdraw_amount ?? 0);
                                            } catch (\Exception $e) {
                                                $totalAmount = 0;
                                            }
                                            
                                            // Final safety check
                                            if (!is_numeric($totalAmount) || !is_finite($totalAmount)) {
                                                $totalAmount = 0;
                                            }
                                            
                                            $formattedTotal = '0.00';
                                            try {
                                                $formattedTotal = number_format((float)$totalAmount, 2);
                                            } catch (\Exception $e) {
                                                $formattedTotal = '0.00';
                                            }
                                        @endphp
                                        ${{ $formattedTotal }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        @endif

        <div class="totals-section">
            @php
                $displayGross = 0;
                $displayGST = 0;
                $displayInvoice = 0;
                $displayPending = 0;
                
                try {
                    $displayGross = floatval($total_Gross_Amount ?? 0);
                } catch (\Exception $e) {
                    $displayGross = 0;
                }
                
                try {
                    $displayGST = floatval($total_GST_amount ?? 0);
                } catch (\Exception $e) {
                    $displayGST = 0;
                }
                
                try {
                    $displayInvoice = floatval($total_Invoice_Amount ?? 0);
                } catch (\Exception $e) {
                    $displayInvoice = 0;
                }
                
                try {
                    $displayPending = floatval($total_Pending_amount ?? 0);
                } catch (\Exception $e) {
                    $displayPending = 0;
                }
            @endphp
            <div class="totals-row">
                <span class="totals-label">Gross Amount:</span>
                <span class="totals-value">${{ number_format($displayGross, 2) }}</span>
            </div>
            <div class="totals-row">
                <span class="totals-label">GST:</span>
                <span class="totals-value">${{ number_format($displayGST, 2) }}</span>
            </div>
            <div class="totals-row">
                <span class="totals-label">Total Invoice Amount:</span>
                <span class="totals-value">${{ number_format($displayInvoice, 2) }}</span>
            </div>
            <div class="totals-row">
                <span class="totals-label">Total Pending Amount:</span>
                <span class="totals-value">${{ number_format($displayPending, 2) }}</span>
            </div>
        </div>

        <div class="payment-instructions">
            <p class="payment-method-highlight">Payment Method: {{ $invoice_payment_method ?? 'N/A' }}</p>
            <p>If you wish to make payment by Direct Debit, use the following details in your Electronic Funds Transfer. Please remember to quote your MATTER NO <strong>{{ $client_matter_display ?? $client_matter_no ?? 'N/A' }}</strong> and advise us by email when you have made the transfer.</p>
            <div class="bank-details">
                <strong>Account Name:</strong> Bansal Immigration<br/>
                <strong>BSB:</strong> 083419<br/>
                <strong>Account Number:</strong> 362421793<br/>
                <strong>Swift Code:</strong> ____________________
            </div>
        </div>
    </div>
</body>
</html>
