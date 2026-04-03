@extends('layouts.crm_client_detail')
@section('title', 'Emails')

@section('styles')
<style>
    /* Fix dropdown menu positioning for action buttons */
    .table-responsive {
        overflow: visible !important;
    }
    
    .common_table .table td {
        overflow: visible !important;
    }
    
    .common_table .table td .dropdown {
        position: relative;
        display: inline-block;
        overflow: visible !important;
    }
    
    /* Dropdown Menu */
    .common_table .dropdown-menu {
        position: absolute !important;
        top: 100% !important;
        right: 0 !important;
        left: auto !important;
        float: none !important;
        min-width: 180px;
        padding: 8px 0;
        margin: 4px 0 0;
        font-size: 14px;
        text-align: left;
        background: #ffffff;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        z-index: 9999 !important;
        transform: none !important;
        max-height: none !important;
        overflow: visible !important;
    }
    
    .common_table .dropdown-menu.show {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    /* Dropdown Items Styling */
    .common_table .dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 20px;
        clear: both;
        color: #495057;
        text-align: inherit;
        white-space: nowrap;
        background-color: transparent;
        border: 0;
        text-decoration: none;
        border-radius: 4px;
        margin: 2px 8px;
        width: calc(100% - 16px);
    }
    
    .common_table .dropdown-item:hover {
        color: #667eea;
        text-decoration: none;
        background: #f8f9fa;
    }
    
    .common_table .dropdown-item:active {
        background: #e9ecef;
    }
    
    .common_table .dropdown-item.has-icon {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .common_table .dropdown-item.has-icon i {
        width: 16px;
        text-align: center;
    }
    
    /* Ensure all dropdown items are visible */
    .common_table .dropdown-menu .dropdown-item {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        height: auto !important;
        min-height: 32px !important;
        line-height: 1.5 !important;
    }
</style>
@endsection
 
@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<div class="row">
				 <div class="col-3 col-md-3 col-lg-3">
			        	@include('../Elements/CRM/setting')
		        </div>       
				<div class="col-9 col-md-9 col-lg-9">
					<div class="card">
						<div class="card-header">
							<h4>All Emails</h4>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table"> 
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Name</th>
										<th>Display Name</th>
										<th>Email Signature</th>
										<th>User Sharing</th>
										<th>Status</th>
									</tr> 
								</thead>
								@if(@$totalData !== 0)
								<tbody class="tdata">	
								@foreach (@$lists as $list)
									<tr id="id_{{ md5(@$list->email) }}">
										<td>{{ @$list->email == "" ? config('constants.empty') : Str::limit(@$list->email, '50', '...') }}</td> 	
										<td>{{ @$list->display_name == "" ? config('constants.empty') : Str::limit(@$list->display_name, '50', '...') }}</td>
										<td>{!! @$list->email_signature == "" ? config('constants.empty') : Str::limit(strip_tags(@$list->email_signature), '80', '...') !!}</td>
										<td>{{ @$list->user_sharing == "" ? config('constants.empty') : Str::limit(@$list->user_sharing, '50', '...') }}</td> 	
										<td>
										<?php
										if($list->status == 1){ echo '<span class=" text-success">Active</span>'; }else{
											echo '<span class=" text-danger">Inactive</span>';
										}
										?>
										</td> 	
									</tr>	
								@endforeach	 
								</tbody>
								@else
								<tbody>
									<tr>
										<td style="text-align:center;" colspan="7">
											No Record found
										</td>
									</tr>
								</tbody>
								@endif
							</table> 
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
 
@endsection