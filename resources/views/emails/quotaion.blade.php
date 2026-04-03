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
$admin = \App\Models\Staff::where('role',1)->where('id',$fetchedData->user_id)->first();
	
	?>
		<div class="invoice_table" style="padding: 10px;">
			<table width="100%" border="0">
				<tbody>
				    
					<tr>
						<td><img style="width:150px;" src="{{ $admin->profile_img }}" alt=""/></td>
						<td>
							
						</td>
						<td style="text-align: right;">
							<span style="font-size:21px;line-height:24px;color:#000;"><b>{{$admin->company_name}}</b></span>
							<p style="font-size: 15px;line-height: 21px;color: #333;font-weight: normal;margin: 10px 0px 0px;"><b>Address:</b> {{$admin->address}}<br/>
							<b>Email:</b> {{$admin->email ?? ''}}<br/>
							<b>Phone:</b> {{$admin->phone}}</p>
						</td>
					</tr>
					<tr>
						<td colspan="3"><hr style="border-color:#000;"/></td>
					</tr>
					<tr>
						<td colspan="3" style="text-align:center;font-size:18px;line-height: 24px;"><b>Quotation #{{$fetchedData->id}}</b></td>
					</tr>
					<tr>
						<td colspan="2">
							<span style="font-size: 18px;line-height: 21px;color: #000;margin: 0px 0px 20px;display:block;"><b>Client Details</b></span>
							<p style="font-size: 13px;line-height: 16px;color: #1a1a1a;font-weight: normal;margin: 10px 0px 5px;"><b>{{@$fetchedData->client->first_name}} {{@$fetchedData->client->first_name}}</b></p>
							<p style="font-size: 14px;line-height: 21px;color: #1a1a1a;font-weight: bold;margin: 0px;">{{@$fetchedData->client->email}}</p>
							<p style="font-size: 13px;line-height: 21px;color: #1a1a1a;font-weight: normal;margin: 0px;">{{@$fetchedData->client->phone}}</p>
						</td>
						<td>
							
							<p style="font-size: 13px;line-height: 16px;color: #000;font-weight: normal;margin: 0px;">Date: <b><?php echo date('Y-m-d', strtotime(@$fetchedData->created_at));?></b></p>
							
						</td>
					</tr>
					
					<tr>
					<td colspan="3" style="padding:10px;"></td>
					<td>
					<tr>
						
						<td class="inv_table" colspan="3">
							<table width="100%" border="0">
								<tbody>
									<tr>
										<td style="padding:0px;">
											<table width="100%" border="0">
												<thead>
													<tr style="background-color: #57c0ef;color: #fff;">
														<th>Product List</th>
														<th>Description</th>
														<th colspan="2">Service Fee</th>
														<th style="border-left: 1px solid #d3cccc;">Discount</th>
														<th>Net Fee</th>
														<th style="border-left: 1px solid #d3cccc;">Exg. Rate</th>
														<th >Amt</th>
													</tr>
												</thead>
												<tbody>
													<?php
								$i=1;
								$l=0;
								$getq = collect(); // QuotationInfo model removed
								$totfare = 0;
								foreach($getq as $q){
									$servicefee = $q->service_fee;
									$discount = $q->discount;
									$exg_rate = $q->exg_rate;
									
									$netfare = $servicefee - $discount;
									$exgrw = $netfare * $exg_rate;
									$totfare += $exgrw;
								$workflowdata = \App\Models\Workflow::where('id',$q->workflow)->first();	
									
								// Partner functionality removed	
									?>
									<tr >
										
										<td class="show_{{$l}}"><div class="productinfo"><div class="productdet"><b>Service</b></div>Partner<div class="prodescription">({{@$workflowdata->name}})</div></div></td>
										<td>{{@$q->description}}</td>
										<td style="border-left: 1px solid #d3cccc;">AUD</td>
										<td style="border-left: 1px solid #d3cccc;">{{number_format($servicefee,2,'.','')}}</td>
										<td style="border-left: 1px solid #d3cccc;">{{number_format($discount,2,'.','')}}</td>
										
										<td class="netfare">{{number_format($netfare,2,'.','')}}</td>
										<td style="border-left: 1px solid #d3cccc;">{{number_format($exg_rate,2,'.','')}}</td>
										<td>{{number_format($exgrw,2,'.','')}}</td>
										
									</tr>
									<?php
									$i++;
									$l++;
								}
								?>
												</tbody>
												<tfoot>
													<tr style="background-color: #57c0ef;color: #fff;">
														<td colspan="7" style="text-align:right;"><b>Grand Total</b></td>
														<td ><b>${{number_format($totfare, 2, '.','')}}</b></td>
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