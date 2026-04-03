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
							<p style="font-size: 15px;line-height: 21px;color: #1a1a1a;font-weight: normal;margin: 10px 0px 0px;"> {{$admin->address}}<br/>
                                {{$admin->state}} {{$admin->city}} {{$admin->zip}}<br/>
							<b>Email:</b> {{$admin->email ?? ''}}<br/>
							<b>Phone:</b> {{$admin->phone}}</p>
						</td>
						<td style="text-align: right;">
							<h2 style="color:#1e40af">Tax Invoice</h2>
                            <p style="font-size: 15px;line-height: 21px;color: #1a1a1a;font-weight: normal;margin: 10px 0px 0px;"><b>ISSUED:</b> {{date('d/m/Y')}}<br/>
                            <b>DUE:</b> {{date('d/m/Y')}}<br/>
							<b>INVOICE NO:</b> {{$record_get[0]->invoice_no}}</p>
						</td>
					</tr>
					<tr>
						<td colspan="3"><!--<hr style="border-color:#000;"/>--></td>
					</tr>
                    <tr>
						<td colspan="3"></td>
					</tr>
                    <tr>
						<td colspan="3"></td>
					</tr>
                    <tr>
						<td colspan="3"></td>
					</tr>
					<tr>
						<td colspan="2">
							<span style="font-size: 18px;line-height: 21px;color: #000;margin: 20px 0px 0px;display:block;"><b>Bill To:</b></span>
                            <p style="font-size: 13px;line-height: 16px;color: #000;font-weight: normal;margin: 0px 0px 5px;">
                                {{@$clientname->first_name}} {{@$clientname->last_name}}<br/>
                                {{$clientname->address}}<br/>
                                {{$clientname->state}} {{$clientname->city}} {{$clientname->zip}}<br/>
                                {{$clientname->country}}
                            </p>
                        </td>
					</tr>
					<td colspan="3" style="padding:10px;"></td>

                    <tr>
                        <td colspan="3">Matter: {{@$clientname->first_name}} {{@$clientname->last_name}}</td>
                    </tr>




					<tr>
					<td colspan="3"><h4>Current Invoice - {{$record_get[0]->invoice_no}}</h4></td>
					</tr>

                    
                    <tr><td colspan="3"></td></tr>
                    <tr><td colspan="3"></td></tr>
                    <tr><td colspan="3"></td></tr>


                    <tr>
						<td class="inv_table" colspan="3">
							<table width="100%" border="0">
								<tfoot>
									<tr>
										<td colspan="56" style="text-align:right;">Total Amount:</td>
										<td style="text-align:right;padding-right:25px;">${{$record_get[0]->deposit_amount}}</td>
									</tr>
								</tfoot>
							</table>
						</td>
					</tr>

                </tbody>
			</table>
		</div>
	</body>
</html>
