@extends('layouts.crm_client_detail')
@section('title', 'Matter')

@section('content')
<style>
    /* Scoped to this page only — avoids breaking sidebar/header dropdowns and global .table styles */
    .matter-index-page .filter_panel {
        margin-bottom: 30px;
        padding: 20px;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border, #c8dcef);
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
        display: none;
    }

    .matter-index-page .filter_panel h4 {
        color: var(--navy, #1e3d60) !important;
        font-size: 1.1rem;
        margin-bottom: 20px;
        font-weight: 600;
    }

    /* Table colours: public/css/crm-theme.css (.adminconsole-features .table-responsive > table) */

    .matter-index-page .form-group label {
        color: var(--text-muted, #5e7a90) !important;
        font-weight: 600 !important;
        margin-bottom: 8px !important;
    }

    .matter-index-page .card-header h4 {
        color: var(--navy, #1e3d60) !important;
        font-weight: 700 !important;
        margin: 0 !important;
    }

    .matter-index-page .dropdown {
        position: relative;
    }

    .matter-index-page .dropdown-menu {
        min-width: 200px !important;
        max-width: 280px !important;
        width: auto !important;
        z-index: 1060 !important;
        background-color: #fff !important;
        border: 1px solid #e9ecef !important;
        border-radius: 6px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        max-height: min(85vh, 520px) !important;
        overflow-y: auto !important;
    }

    .matter-index-page .dropdown-item {
        padding: 8px 12px !important;
        font-size: 0.9rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 100% !important;
    }

    .matter-index-page .dropdown-item i {
        margin-right: 6px !important;
        width: 14px !important;
        text-align: center !important;
    }

    .matter-index-page .dropdown-item.has-icon {
        display: flex !important;
        align-items: center !important;
        padding: 8px 12px !important;
        font-size: 0.8rem !important;
        line-height: 1.2 !important;
        position: relative !important;
        min-height: 32px !important;
        max-width: 100% !important;
        overflow: visible !important;
        text-overflow: clip !important;
        white-space: nowrap !important;
    }

    .matter-index-page .dropdown-item.has-icon i {
        width: 14px !important;
        height: 14px !important;
        flex-shrink: 0 !important;
        text-align: center !important;
        display: inline-block !important;
        margin-right: 8px !important;
        position: static !important;
    }

    .matter-index-page .dropdown-menu .dropdown-item {
        visibility: visible !important;
        opacity: 1 !important;
    }

    .matter-index-page .table-responsive.common_table {
        overflow: visible !important;
    }

    .matter-index-page .table tbody tr {
        position: relative !important;
    }

    .matter-index-page .table tbody tr td:last-child {
        overflow: visible !important;
        position: relative !important;
    }

    .matter-index-page .dropdown-item span,
    .matter-index-page .dropdown-item {
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }

    /*
     * Matter list only: allow menus to paint past .main-content { overflow: hidden }
     * and past the card body / footer (grey bar) without affecting other CRM pages.
     */
    .matter-index-layout > .main-content {
        overflow: visible !important;
    }

    .matter-index-layout .matter-index-page .card,
    .matter-index-layout .matter-index-page .card-body {
        overflow: visible !important;
    }
</style>
<div class="crm-container matter-index-layout">
	<div class="main-content adminconsole-features">
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
				<div class="matter-index-page">
					<div class="card">
						<div class="card-header">
							<h4>All Matters</h4>
                            <div class="card-header-action">
                                <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn mr-2"><i class="fas fa-filter"></i> Filter</a>
                                <a href="{{route('adminconsole.features.matter.create')}}" class="btn btn-primary">Create Matter</a>
							</div>
						</div>
						<div class="card-body">
                            <div class="filter_panel"><h4>Search</h4>
                                <form action="{{route('adminconsole.features.matter.index')}}" method="get">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="title" class="col-form-label" style="color:#495057 !important; font-weight: 500 !important;">Matter Name</label>
                                                <input type="text" name="title" value="{{ old('title', Request::get('title')) }}" class="form-control" data-valid="" autocomplete="off" placeholder="Select Matter" id="title">
                                            </div>
                                        </div>
                                        <div class="col-md-6" style="margin-top:35px;">
                                            <button type="submit" class="btn btn-primary btn-theme-lg">Search</button>
                                            <a class="btn btn-info" href="{{route('adminconsole.features.matter.index')}}">Reset</a>
                                        </div>
                                    </div>
                                </form>
                            </div>

							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Matter Name</th>
										<th></th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}">
										<td>{{ @$list->title == "" ? config('constants.empty') : Str::limit(@$list->title, '50', '...') }}</td>
										<td class="text-nowrap">
											<div class="dropdown d-inline-block">
												<button class="btn btn-primary dropdown-toggle matter-action-dropdown-toggle" type="button" id="matterAction_{{ $list->id }}"
													data-bs-toggle="dropdown"
													data-bs-popper-config='{"strategy":"fixed"}'
													aria-haspopup="true"
													aria-expanded="false">Action</button>
												<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="matterAction_{{ $list->id }}">
													<li><a class="dropdown-item has-icon" href="{{route('adminconsole.features.matter.edit', base64_encode(convert_uuencode(@$list->id)))}}"><i class="far fa-edit"></i> Edit</a></li>
													<li><a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'matters')"><i class="fas fa-trash"></i> Delete</a></li>
													<?php
													$hasTemplate = \App\Models\EmailTemplate::forMatter($list->id)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->exists();
													?>
													@if($hasTemplate)
													<?php
													$Template_info = \App\Models\EmailTemplate::forMatter($list->id)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->first();
													?>
													<li><a class="dropdown-item has-icon" href="{{route('adminconsole.features.matteremailtemplate.edit', [$Template_info->id, $list->id])}}"><i class="far fa-edit"></i> Edit First Email</a></li>
													@else
													<li><a class="dropdown-item has-icon" href="{{ route('adminconsole.features.matteremailtemplate.create', ['matter_id' => @$list->id]) }}"><i class="far fa-edit"></i> Create First Email</a></li>
													@endif

													<li><a class="dropdown-item has-icon" href="{{route('upload_checklists.matter', @$list->id)}}"><i class="fas fa-list"></i> Matter Checklist</a></li>
													<li><a class="dropdown-item has-icon" href="{{route('adminconsole.features.matterotheremailtemplate.index', @$list->id)}}"><i class="fas fa-envelope"></i> Email Templates</a></li>
												</ul>
											</div>
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

                        <div class="card-footer">
							{!! $lists->appends(\Request::except('page'))->render() !!}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
    $('.matter-index-page .filter_btn').on('click', function(){
		$('.matter-index-page .filter_panel').toggle();
	});

	$('.cb-element').change(function () {
        if ($('.cb-element:checked').length == $('.cb-element').length){
            $('#checkbox-all').prop('checked',true);
        } else {
            $('#checkbox-all').prop('checked',false);
        }
    });
});
</script>
@endpush
