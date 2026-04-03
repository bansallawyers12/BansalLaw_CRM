<!doctype html>
<html>
	<head>
		<style>
			body {
				font-family: 'Helvetica Neue', 'Arial', sans-serif;
				margin: 0;
				padding: 0;
				color: #333;
				line-height: 1.6;
			}
			.invoice_table {
				max-width: 800px;
				margin: 0 auto;
			}
			.header-section {
				border-bottom: 3px solid #3abaf4;
				padding-bottom: 15px;
				margin-bottom: 20px;
			}
			.company-info {
				font-size: 14px;
				line-height: 1.8;
				color: #1f2937;
				margin-top: 10px;
			}
			.company-name {
				font-size: 16px;
				font-weight: 700;
				color: #333;
				margin-bottom: 5px;
			}
			.document-title {
				font-size: 28px;
				font-weight: 700;
				color: #3abaf4;
				margin: 0 0 15px 0;
				letter-spacing: -0.5px;
			}
			.document-info {
				font-size: 14px;
				line-height: 1.9;
				color: #1f2937;
			}
			.document-info b {
				color: #111827;
				font-weight: 600;
			}
			.section-title {
				font-size: 16px;
				font-weight: 700;
				color: #333;
				margin: 15px 0 10px 0;
				padding-bottom: 8px;
				border-bottom: 2px solid #e0e0e0;
			}
			.bill-to-section {
				background: #f9f9f9;
				padding: 15px;
				border-radius: 6px;
				margin: 15px 0;
			}
			.bill-to-label {
				font-size: 14px;
				font-weight: 700;
				color: #333;
				margin-bottom: 8px;
			}
			.bill-to-content {
				font-size: 14px;
				line-height: 1.7;
				color: #1f2937;
			}
			.matter-info {
				font-size: 14px;
				color: #1f2937;
				margin: 10px 0;
			}
			.reference-box {
				background: #f0f8ff;
				padding: 10px 15px;
				border-left: 4px solid #3abaf4;
				margin: 10px 0;
				font-size: 14px;
			}
			.reference-box strong {
				color: #333;
			}
			.ledger-table-wrapper {
				margin: 10px 0;
				border: 1px solid #ddd;
				border-radius: 6px;
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
				padding: 12px 10px;
				text-align: left;
				font-size: 12px;
				font-weight: 700;
				color: #ffffff !important;
				text-transform: uppercase;
				letter-spacing: 0.3px;
			}
			.ledger-table thead th:nth-child(4) {
				text-align: right;
			}
			.ledger-table tbody tr {
				border-bottom: 1px solid #eee;
			}
			.ledger-table tbody tr:last-child {
				border-bottom: none;
			}
			.ledger-table tbody tr:hover {
				background: #f9f9f9;
			}
			.ledger-table tbody td {
				padding: 12px 10px;
				font-size: 13px;
				color: #1a1a1a;
			}
			.ledger-table tbody td:nth-child(4) {
				text-align: right;
				font-weight: 600;
				color: #333;
			}
			.payment-instructions {
				background: #fff9e6;
				border: 1px solid #ffe082;
				border-radius: 6px;
				padding: 20px;
				margin: 30px 0;
			}
			.payment-instructions p {
				margin: 0 0 12px 0;
				font-size: 14px;
				line-height: 1.7;
				color: #1a1a1a;
			}
			.payment-instructions p:last-child {
				margin-bottom: 0;
			}
			.bank-details {
				background: #fff;
				border: 1px solid #e0e0e0;
				border-radius: 4px;
				padding: 15px;
				margin-top: 15px;
				font-family: 'Courier New', monospace;
				font-size: 13px;
				line-height: 1.9;
			}
			.bank-details strong {
				color: #333;
			}
		</style>
	</head>
	<body>
	<?php
	$admin = \App\Models\Staff::where('role',1)->first();
	?>
		<div class="invoice_table" style="padding: 20px;">
			<table width="100%" border="0">
				<tbody>
					<tr class="header-section">
						<td style="text-align: left; width: 50%; vertical-align: top;">
							@php
								$logoPath = public_path('img/logo.png');
								$logoData = '';
								if(file_exists($logoPath)) {
									$logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
								}
							@endphp
							@if($logoData)
								<img width="90" style="height:auto;display:block;margin-bottom:15px;" src="{{$logoData}}" alt="Logo"/>
							@else
								<div style="width:90px;height:60px;background:#3abaf4;display:block;margin-bottom:15px;"></div>
							@endif
							<div class="company-name">BANSAL IMMIGRATION</div>
							<div class="company-info">
								Level 8,278 Collins Street<br/>
								Melbourne VIC 3000<br/>
								E-mail: invoice@bansalimmigration.com.au<br/>
								Phone: 03 96021330
							</div>
						</td>
						<td style="text-align: right; width: 50%; vertical-align: top;">
							<h1 class="document-title">Client Fund Receipt</h1>
							<div class="document-info">
								<b>ABN</b> 70 958 120 428<br/>
								<b>Receipt Date:</b> {{@$record_get->trans_date ? $record_get->trans_date : date('d/m/Y')}}<br/>
								<b>Receipt No:</b> {{@$record_get->trans_no}}
							</div>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<div class="bill-to-section">
								<div class="bill-to-label">Received From:</div>
								<div class="bill-to-content">
									<strong>{{@$clientname->first_name}} {{@$clientname->last_name}}</strong><br/>
									@if(!empty($clientname->address))
										{{$clientname->address}}<br/>
									@endif
									@php
										$addressLine = trim(
											(!empty($clientname->city) ? $clientname->city : '') . 
											(!empty($clientname->state) ? ' ' . $clientname->state : '') . 
											(!empty($clientname->zip) ? ' ' . $clientname->zip : '')
										);
									@endphp
									@if($addressLine)
										{{$addressLine}}<br/>
									@endif
									@if(!empty($clientname->country))
										{{$clientname->country}}
									@endif
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<div class="matter-info">
								<strong>Matter:</strong> {{ $client_matter_display ?? 'N/A' }}
							</div>
						</td>
					</tr>
                    <tr>
                        <td colspan="2">
                            <h3 class="section-title">Receipt Details</h3>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="ledger-table-wrapper">
                                <table class="ledger-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th style="text-align: right;">Amount Received</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if( $record_get){ ?>
                                            <tr>
                                                <td>{{@$record_get->trans_date}}</td>
                                                <td>{{@$record_get->client_fund_ledger_type}}</td>
                                                <td>
                                                    {{@$record_get->description}}
                                                    @if(!empty($record_get->payment_method))
                                                        <div style="margin-top: 6px; font-size: 12px; color: #555;"><strong>Payment method:</strong> {{ $record_get->payment_method }}</div>
                                                    @endif
                                                    @if(!empty($record_get->eftpos_surcharge_amount) && floatval($record_get->eftpos_surcharge_amount) > 0)
                                                        <div style="margin-top: 4px; font-size: 12px; color: #555;"><strong>Card surcharge:</strong> ${{ number_format((float) $record_get->eftpos_surcharge_amount, 2) }}</div>
                                                    @endif
                                                </td>
                                                <td style="text-align: right; font-weight: 700; font-size: 16px; color: #2e7d32;">
                                                    ${{number_format($record_get->deposit_amount,2)}}
                                                </td>
                                            </tr>
                                        <?php
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div style="text-align: right; padding: 15px; background: #f0f8ff; border-radius: 6px; margin-top: 10px;">
                                <div style="font-size: 13px; color: #1a1a1a; margin-bottom: 3px;">Total Amount Received</div>
                                <div style="font-size: 20px; font-weight: 700; color: #2e7d32;">
                                    ${{number_format($record_get->deposit_amount,2)}}
                                </div>
                            </div>
                        </td>
                    </tr>
					<tr>
						<td colspan="2">
							<div style="background: #f0f8ff; border-left: 4px solid #3abaf4; padding: 15px; margin-top: 20px; font-size: 13px; color: #1f2937;">
								<p style="margin: 0;"><strong style="color: #111827;">Receipt Acknowledgement:</strong> This receipt confirms that we have received the above amount into our Client Trust Account for matter <strong>{{ $client_matter_no }}</strong>. These funds will be applied to your matter as professional fees, disbursements, and charges are incurred.</p>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</body>
</html>

