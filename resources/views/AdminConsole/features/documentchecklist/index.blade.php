@extends('layouts.crm_client_detail')
@section('title', 'Document Checklist')

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
							<h4>Document checklist</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.documentchecklist.create')}}" class="btn btn-primary"><i class="fa fa-plus"></i> Add</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
										<!--<th class="text-center" style="width:30px;">
											<div class="custom-checkbox custom-checkbox-table custom-control">
												<input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="custom-control-input" id="checkbox-all">
												<label for="checkbox-all" class="custom-control-label">&nbsp;</label>
											</div>
										</th>-->
										<th>Name</th>
										<th>Document type</th>
										<th class="text-nowrap">Action</th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}">
										<!--<td class="text-center">
											<div class="custom-checkbox custom-control">
											{{--	<input data-id="{{@$list->id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" type="checkbox" data-checkboxes="mygroup" class="cb-element custom-control-input" id="checkbox-{{$i}}">
												<label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>--}}
											</div>
										</td>-->
										<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td>
										<td>
                                            <?php
                                            if( isset($list->doc_type) && $list->doc_type !="" ){
                                                if($list->doc_type == 1 ){
                                                    echo "Personal";
                                                } else if($list->doc_type == 2 ){
                                                    echo "Visa";
                                                } else if($list->doc_type == 3 ){
                                                    echo "Nomination";
                                                }
                                            }?>
                                        </td>
                                        <td class="text-nowrap">
											<div class="dropdown d-inline-block">
												<button class="btn btn-primary dropdown-toggle" type="button" id="docChkAction_{{ $list->id }}"
													data-bs-toggle="dropdown"
													data-bs-popper-config='{"strategy":"fixed"}'
													aria-haspopup="true"
													aria-expanded="false">Action</button>
												<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="docChkAction_{{ $list->id }}">
													<li><a class="dropdown-item has-icon" href="{{route('adminconsole.features.documentchecklist.edit', base64_encode(convert_uuencode(@$list->id)))}}"><i class="far fa-edit"></i> Edit</a></li>
													<li><a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'document_checklists')"><i class="fas fa-trash"></i> Delete</a></li>
												</ul>
											</div>
										</td>
									</tr>
								@endforeach
								</tbody>
								@else
								<tbody>
									<tr>
										<td class="text-center" colspan="3">
											No records found
										</td>
									</tr>
								</tbody>
								@endif
							</table>
						</div>

                        <div class="card-footer">
							{!! $lists->appends(\Request::except('page'))->render() !!}
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
	/* if ($('.cb-element:checked').length > 0){
			$('.is_checked_client').show();
			$('.is_checked_clientn').hide();
		}else{
			$('.is_checked_client').hide();
			$('.is_checked_clientn').show();
		} */
	});
});
</script>
@endpush
