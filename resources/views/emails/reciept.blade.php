<!doctype html>
<html>
	<head>
		<style>
			body{font-family: 'Open Sans', sans-serif;margin:0px;}
			.inv_table{vertical-align: top;}
			.invoice_table table{border-collapse: collapse;}
			.inv_table table thead{background:#eee;}
			.inv_table table thead tr th{padding:10px;text-align:left;}
			.inv_table table tbody tr td{padding:8px;}
			.inv_table table thead tr th, .inv_table table tbody tr td{font-size:14px;line-height: 18px;vertical-align: top;}
			.inv_table table tbody tr.total_val td{background:#ccc;padding: 20px 10px;}
			
		</style>
	</head>
	<body>
	<?php
	$admin = \App\Models\Staff::where('role',1)->first();
	
	?>
		<div class="invoice_table" style="padding: 10px;">
			<table width="100%" border="0">
				<tbody>
					<tr>
						<td><img src="{{URL::to('/public/img/logo.png')}}" alt="Company Logo" style="max-width: 180px; height: auto;"/></td>
						<td>
							<span style="font-size:21px;line-height:24px;color:#000;"><b>{{$admin->company_name}}</b></span>
							<p style="font-size: 15px;line-height: 21px;color: #333;font-weight: normal;margin: 10px 0px 0px;"><b>Address:</b> {{$admin->address}}<br/>
							<b>Email:</b> {{$admin->email ?? ''}}<br/>
							<b>Phone:</b> {{$admin->phone}}</p>
						</td>
						<td style="text-align: right;">
							<h2 style="color:#3abaf4">RECEIPT</h2>
						</td>
					</tr>
					<tr>
						<td colspan="3"><hr style="border-color:#000;"/></td>
					</tr>
					<tr>
						<td colspan="2">
							<span style="font-size: 18px;line-height: 21px;color: #000;margin: 0px 0px 20px;display:block;"><b>Received From</b></span>
							<p style="font-size: 13px;line-height: 16px;color: #1a1a1a;font-weight: normal;margin: 10px 0px 5px;"><b>{{@$fetchedData->invoice->customer->first_name}} {{@$fetchedData->invoice->customer->first_name}}</b></p>
							<p style="font-size: 14px;line-height: 21px;color: #1a1a1a;font-weight: bold;margin: 0px;">{{@$fetchedData->invoice->customer->email}}</p>
							<p style="font-size: 13px;line-height: 21px;color: #1a1a1a;font-weight: normal;margin: 0px;">{{@$fetchedData->invoice->customer->phone}}</p>
						</td>
						<td>
							<span style="font-size: 18px;line-height: 21px;color: #000;margin: 0px 0px 20px;display:block;"><b>Receipt Details</b></span>
							<p style="font-size: 13px;line-height: 16px;color: #000;font-weight: normal;margin: 10px 0px 5px;">Receipt No: <b>#<?php echo $fetchedData->id;?></b></p>
							<p style="font-size: 13px;line-height: 16px;color: #000;font-weight: normal;margin: 0px;">Receipt Date: <b><?php echo date('Y-m-d', strtotime(@$fetchedData->created_at));?></b></p>
							
						</td>
					</tr>
					<td colspan="3" style="padding:10px;"><hr></td>
					<tr>
					<td colspan="3"><h4>Payment Details</h4></td>
					</tr>
					<tr>
						
						<td class="inv_table" colspan="3">
							<table width="100%" border="1">
								<tbody>
									<tr>
										<td style="padding:0px;">
											<table width="100%" border="0">
												<thead>
													<tr>
														<th>Received For</th>
														<th>Payment Method</th>
														<th>Payment Date </th>
														<th>Amount Paid</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td>Invoice No: <b>#<?php echo $fetchedData->invoice_id;?></b><br>Invoice Amount: <b>$<?php echo $fetchedData->amount_rec;?></b></td>
														<td><?php echo $fetchedData->payment_mode;?></td>
														<td><?php echo $fetchedData->payment_date;?></td>
														<td><b>$<?php echo $fetchedData->amount_rec;?></b></td>
													</tr>
													
												</tbody>
												<tfoot>
													<tr>
														<td colspan="3"><b>Grand Total</b></td>
														<td ><b>$<?php echo $fetchedData->amount_rec;?></b></td>
													</tr>
												</tfoot>
											</table>
										</td>										
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
					
				</tbody>
			</table>
		</div>
	</body>
</html>
