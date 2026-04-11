@extends('layouts.crm_client_detail')
@section('title', 'Matter Document Category')

@section('content')

<!-- Main Content -->
<div class="main-content adminconsole-features">
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
							<h4>Matter document category</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.matterdocumenttype.create')}}" class="btn btn-primary"><i class="fa fa-plus"></i> Add</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Title</th>
										<th>Client name</th>
										<th>Client matter name</th>
										<th class="text-nowrap">Action</th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}">
                                        <td><?php if(isset($list->title)){ echo $list->title; }?></td>
                                        <td><?php
                                            if(isset($list->client_id) && $list->client_id != ''){
                                                $admin = \App\Models\Admin::select('first_name','last_name')->where('id', $list->client_id)->first();
                                                if($admin){
                                                    echo $admin->first_name.' '.$admin->last_name;
                                                } else {
                                                    echo 'NA';
                                                }
                                            } else {
                                                echo 'Common For All Clients';
                                            }?>
                                        </td>

                                        <td><?php
                                            if(isset($list->client_matter_id) && $list->client_matter_id != ''){
                                                $clientMatterInfo = \App\Models\ClientMatter::select('sel_matter_id')->where('id', $list->client_matter_id)->first();
                                                if($clientMatterInfo){
                                                    $matterInfo = \App\Models\Matter::select('title','nick_name')->where('id', $clientMatterInfo->sel_matter_id)->first();
                                                    if($matterInfo){
                                                        echo $matterInfo->title.' ('.$matterInfo->nick_name.')';
                                                    } else {
                                                         echo 'NA';
                                                    }
                                                } else {
                                                    echo 'Common For All Client Matters';
                                                }
                                            } else {
                                                echo 'Common For All Client Matters';
                                            }?>
                                        </td>
										<td class="text-nowrap">
											<div class="dropdown d-inline-block">
												<button class="btn btn-primary dropdown-toggle" type="button" id="matterDocTypeAction_{{ $list->id }}"
													data-bs-toggle="dropdown"
													data-bs-popper-config='{"strategy":"fixed"}'
													aria-haspopup="true"
													aria-expanded="false">Action</button>
												<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="matterDocTypeAction_{{ $list->id }}">
													<li><a class="dropdown-item has-icon" href="{{route('adminconsole.features.matterdocumenttype.edit', base64_encode(convert_uuencode(@$list->id)))}}"><i class="far fa-edit"></i> Edit</a></li>
												</ul>
											</div>
										</td>
									</tr>
								@endforeach
								</tbody>
								@else
								<tbody>
									<tr>
										<td class="text-center" colspan="4">
											No records found
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
@push('scripts')
<script>
jQuery(document).ready(function($){
	$('.cb-element').change(function () {
	if ($('.cb-element:checked').length == $('.cb-element').length){
	  $('#checkbox-all').prop('checked',true);
	}
	else {
	  $('#checkbox-all').prop('checked',false);
	}

	});
});
</script>
@endpush
