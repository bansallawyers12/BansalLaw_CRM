@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Leads')

@section('content')
<style>.popover {max-width:700px;}
.timeline{margin:0 0 45px;padding:0;position:relative}.timeline::before{border-radius:.25rem;background:#dee2e6;bottom:0;content:'';left:31px;margin:0;position:absolute;top:0;width:4px}.timeline>div{margin-bottom:15px;margin-right:10px;position:relative}.timeline>div::after,.timeline>div::before{content:"";display:table}.timeline>div>.timeline-item{box-shadow:0 0 1px rgba(0,0,0,.125),0 1px 3px rgba(0,0,0,.2);border-radius:.25rem;background:#fff;color:#495057;margin-left:60px;margin-right:15px;margin-top:0;padding:0;position:relative}.timeline>div>.timeline-item>.time{color:#999;float:right;font-size:12px;padding:10px}.timeline>div>.timeline-item>.timeline-header{border-bottom:1px solid rgba(0,0,0,.125);color:#495057;font-size:16px;line-height:1.1;margin:0;padding:10px}.timeline>div>.timeline-item>.timeline-header>a{font-weight:600}.timeline>div>.timeline-item>.timeline-body,.timeline>div>.timeline-item>.timeline-footer{padding:10px}.timeline>div>.timeline-item>.timeline-body>img{margin:10px}.timeline>div>.timeline-item>.timeline-body ol,.timeline>div>.timeline-item>.timeline-body ul,.timeline>div>.timeline-item>.timeline-body>dl{margin:0}.timeline>div>.timeline-item>.timeline-footer>a{color:#fff}.timeline>div>.fa,.timeline>div>.fab,.timeline>div>.far,.timeline>div>.fas,.timeline>div>.glyphicon,.timeline>div>.ion{background:#adb5bd;border-radius:50%;font-size:15px;height:30px;left:18px;line-height:30px;position:absolute;text-align:center;top:0;width:30px}.timeline>.time-label>span{border-radius:4px;background-color:#fff;display:inline-block;font-weight:600;padding:5px}.timeline-inverse>div>.timeline-item{box-shadow:none;background:#f8f9fa;border:1px solid #dee2e6}.timeline-inverse>div>.timeline-item>.timeline-header{border-bottom-color:#dee2e6}
.timeline i{color: #fff;}

/* Fix text contrast issues */
.card-body .float-left {
    color: #495057 !important;
    font-weight: 600;
}

.card-body .float-right.text-muted {
    color: #495057 !important;
    font-weight: 400;
}

.card-body .client_info_tags span {
    color: #495057 !important;
    font-weight: 600;
}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="row">
				<div class="col-md-12">
					<!-- Flash Message Start -->
					<div class="server-error">
						@include('../Elements/flash-message')
					</div>
					<!-- Flash Message End -->
				</div>									
			</div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
					<!-- Profile Image -->
					<div class="card author-box">
						<div class="card-body">
							<div class="author-box-center">
								<span class="author-avtar" style="background: rgb(68, 182, 174);"><b>{{substr($fetchedData->first_name, 0, 1)}}{{substr($fetchedData->last_name, 0, 1)}}</b></span>
								<div class="clearfix"></div>
								<div class="author-box-name">
									<a href="#">{{$fetchedData->first_name}} {{$fetchedData->last_name}}</a>
									<p class="text-muted text-center"><i class="fa-solid fa-ticket"></i> LEAD-{{str_pad($fetchedData->id, 3, '0', STR_PAD_LEFT)}}</p>
								</div>
								<div class="author-mail_sms">
								<a href="javascript:;" data-id="{{@$fetchedData->id}}" data-email="{{@$fetchedData->email}}" data-name="{{@$fetchedData->first_name}} {{@$fetchedData->last_name}}" class="clientemail" title="Compose Mail"><i class="fa-solid fa-envelope"></i></a>
								<a href="{{route('leads.edit', base64_encode(convert_uuencode(@$fetchedData->id)))}}" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>								
							</div>
							</div>
							
							
						</div>
					  <!-- /.card-body -->
					</div>
					<!-- /.card -->

					<!-- About Me Box -->
					<div class="card">
						<div class="card-header">
							<h5 class="">Personal Info</h5>
						</div>
					  <!-- /.card-header -->
						<div class="card-body">
							<div class="row">
							    <div class="col-md-12">
								@if($fetchedData->phone != '')
								<p class="clearfix"> 
    								<span class="float-left">Phone:</span>
    								<span class="float-right text-muted">{{@$fetchedData->country_code}} {{@$fetchedData->phone}}</span>
    							</p>
								@endif
								@if($fetchedData->email != '')
									<p class="clearfix"> 
    								<span class="float-left">Email:</span>
    								<span class="float-right text-muted">{{@$fetchedData->email}}</span>
    							</p>
								@endif
								@if($fetchedData->gender != '')
									<p class="clearfix"> 
    								<span class="float-left">Gender:</span>
    								<span class="float-right text-muted">{{@$fetchedData->gender}}</span>
    							</p>
								@endif
								@if($fetchedData->dob != '')
									<p class="clearfix"> 
    								<span class="float-left">Date of Birth:</span>
    								<span class="float-right text-muted">
    								    <?php
										if($fetchedData->dob != ''){
										    echo $dob = date('d/m/Y', strtotime($fetchedData->dob));
										}
										?>
    								    </span>
    							</p>
								@endif
								@if($fetchedData->marital_status != '')
								<p class="clearfix"> 
    								<span class="float-left">Marital Status:</span>
    								<span class="float-right text-muted">
    								    {{@$fetchedData->marital_status}}</span>
    							</p>
								@endif
								@if($fetchedData->visa_expiry_date != '')
								    <p class="clearfix"> 
								<span class="float-left">Visa Expiry Date:</span>
								<span class="float-right text-muted">
								     <?php
										if($fetchedData->visa_expiry_date != ''){
										    echo date('d/m/Y', strtotime($fetchedData->visa_expiry_date));
										}
										?>
								 </span>
							</p>
								@endif
								</div>
								<?php
									$assignee = \App\Models\Staff::where('id',@$fetchedData->user_id)->first();
								?>
								<div class="col-md-12"> 
									<div class="client_assign client_info_tags"> 
									<span class="">Assignee:</span>
										@if($assignee)
										<div class="client_info">
											<div class="cl_logo">{{substr(@$assignee->first_name, 0, 1)}}</div>
											<div class="cl_name">
												<span class="name">{{@$assignee->first_name}}</span>
												<span class="email">{{@$assignee->email}}</span>
											</div>
										</div>
										@else
											-
										@endif
									</div>
								</div>
							</div>
						</div>
					  <!-- /.card-body -->
					</div>
					<!-- /.card -->
				</div>
				<!-- /.col -->
				<div class="col-md-9"> 
					<div class="card card-danger card-outline">
						<div class="card-header p-2">
							<h5 class="">Followup History</h5>
						</div><!-- /.card-header -->						
						<div class="card-body">
							
							<div class="followup_btn"> 
								<ul class="navbar-nav" style="display: block;">
									<li class="nav-item d-sm-inline-block update_stage">
										<a style="background: #f59a0e;border-radius: 4px;padding: 7px 10px;font-size: 14px;line-height: 18px;color: #fff;border: 0px;" class="nav-link opennotepopup" data-notename="Others" data-notetype="others" href="javascript:;">Add Note</a>
									</li>
								@if($fetchedData->converted == 0)
								@php
									$historyLeadHasAssignedMatter = \App\Models\ClientMatter::clientHasActiveAssignedMatter((int) $fetchedData->id);
								@endphp
								<li class="nav-item d-sm-inline-block converclient">
								    <form method="POST" action="{{route('leads.convert_single')}}" class="cdn-convert-lead-to-client-form" style="display: inline;"
								          data-has-assigned-matter="{{ $historyLeadHasAssignedMatter ? '1' : '0' }}"
								          data-edit-url="{{ route('clients.edit', base64_encode(convert_uuencode($fetchedData->id))) }}">
								        @csrf
								        <input type="hidden" name="lead_id" value="{{base64_encode(convert_uuencode($fetchedData->id))}}">
								        <button type="submit" style="background: #54ca68;border-radius: 4px;padding: 7px 10px;font-size: 14px;line-height: 18px;color: #fff;border: 0px;cursor: pointer;" class="nav-link">
								            <i class="fa-solid fa-user"></i> Convert To Client
								        </button>
								    </form>
								    </li>
									    @endif
								</ul> 
							</div>
							<div class="history_timeline">
								<ul class="nav nav-tabs" id="myTab" role="tablist">
									<li class="nav-item "><a class="nav-link active" href="#timeline" data-bs-toggle="tab">History</a></li>
									<li class="nav-item"><a class="nav-link" href="#emails" data-bs-toggle="tab"><i class="fa-solid fa-inbox"></i> Emails</a></li>
								</ul>
								<div class="tab-content">								
									<div class="active tab-pane" id="timeline">
										<!-- The timeline -->
										<div class="timeline timeline-inverse followuphistory">
											<!-- END timeline item -->
											<div>
												<i class="fa-regular fa-clock bg-gray"></i>
											</div>
										</div>
									</div>
									<!-- Emails Tab -->
									<div class="tab-pane" id="emails">
										<div style="padding: 20px;">
											@include('crm.emails_lead')
										</div>
									</div>
								</div>
								<!-- /.tab-content -->
							</div>
						</div><!-- /.card-body -->
					</div>
					<!-- /.nav-tabs-custom -->
				</div>
				<!-- /.col -->
			</div>
		</div>
	</section>
</div>

<div id="myAddnotes" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header"> 
			<h4 class="modal-title">Modal Header</h4>
				<button type="button" class="close" data-bs-dismiss="modal">&times;</button>
				
			</div>
			<form action="{{ url('/create-note') }}" method="POST" name="add-note" autocomplete="off" enctype="multipart/form-data" id="addnoteform">
				@csrf
			<div class="modal-body">
				<div class="customerror"></div> 
				<div class="form-group row">
					<div class="col-sm-12">
						<input id="task_group" name="task_group" type="hidden" value="Others">
						<input type="hidden" name="vtype" value="lead">
						<input type="hidden" name="client_id" value="{{ (int) $fetchedData->id }}">
						<input type="hidden" name="lead_id" value="{{ (int) $fetchedData->id }}">
						<textarea id="description" name="description" class="form-control tinymce-editor" placeholder="Add note" data-valid="required"></textarea>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" onclick="return customValidate('add-note');"><i class="fa-solid fa-floppy-disk"></i> Save</button>
			</div>
			</form>
		</div>
	</div>
</div>

<div id="emailmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true" data-staff-signature="{{ auth()->user()->email_signature ?? '' }}" data-signature-prefill="allow">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="clientModalLabel">Compose Email</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" name="sendmail" action="{{ route('clients.sendmail') }}" autocomplete="off" enctype="multipart/form-data" id="lead_history_email_compose">
					@csrf
				<input name="type" type="hidden" value="lead">
				<input name="mail_type" type="hidden" value="2">
				<input name="mail_body_type" type="hidden" value="sent">
				<input name="client_id" type="hidden" value="{{ @$fetchedData->id }}">
				<input name="lead_id" type="hidden" value="{{ @$fetchedData->id }}">
				<input name="email_to[]" type="hidden" value="{{ @$fetchedData->id }}">
				@php
				    $leadComposeMatters = \Illuminate\Support\Facades\Schema::hasTable('client_matters')
				        ? \App\Models\ClientMatter::where('client_id', $fetchedData->id)
				            ->where('matter_status', 1)
				            ->orderByDesc('id')
				            ->get()
				        : collect();
				    $leadDefaultMatterId = optional($leadComposeMatters->first())->id;
				@endphp
				<input type="hidden" name="compose_client_matter_id" id="compose_client_matter_id" value="{{ $leadDefaultMatterId ?? '' }}">
					<div class="row">
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_from_lead">From <span class="span_req">*</span></label>
								@include('partials.email-from-compose', ['email_from_id' => 'email_from_lead'])
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="compose_client_matter_select_lead">Matter</label>
								<select id="compose_client_matter_select_lead" class="form-control">
									<option value="">All matters</option>
									@foreach($leadComposeMatters as $leadMatter)
									<option value="{{ $leadMatter->id }}" @selected((int) $leadDefaultMatterId === (int) $leadMatter->id)>
										{{ $leadMatter->client_unique_matter_no ?: ('Matter #'.$leadMatter->id) }}
									</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_to">To <span class="span_req">*</span></label>
								<input type="email" id="email_to" value="{{ @$fetchedData->email }}" class="form-control" readonly placeholder="">
								
								@if ($errors->has('email_to'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_to') }}</strong>
									</span> 
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_cc_lead">CC</label>
								<select multiple class="js-data-example-ajaxcc" name="email_cc[]" id="email_cc_lead"></select>
							</div>
						</div>
						
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="template">Templates </label>
								<select data-valid="" class="form-control crm-ts-plain selecttemplate" name="template">
									<option value="">Select</option>
									@foreach(\App\Models\EmailTemplate::crm()->orderBy('id', 'desc')->get() as $list)
										<option value="{{$list->id}}">{{$list->name}}</option>
									@endforeach
								</select>
								
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="compose_email_subject">Subject <span class="span_req">*</span></label>
								<input type="text" name="subject" id="compose_email_subject" value="" class="form-control selectedsubject" data-valid="required" autocomplete="off" placeholder="Enter Subject">
								@if ($errors->has('subject'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('subject') }}</strong>
									</span> 
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="compose_email_message">Message <span class="span_req">*</span></label>
								<textarea class="tinymce-editor selectedmessage" id="compose_email_message" name="message" data-valid="required"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>  
								@endif
							</div>
						</div>
						@include('crm.partials.compose-email-attachments')
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="saveComposeEmail()" type="button" class="btn btn-primary">Send</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/crm/compose-matter-documents.js') }}"></script>
<script> 

var lead_id = '{{base64_encode(convert_uuencode(@$fetchedData->id))}}';
window.crmLeadHistoryNoteEncodedId = lead_id;
window.ClientDetailConfig = window.ClientDetailConfig || {};
window.ClientDetailConfig.urls = Object.assign({}, window.ClientDetailConfig.urls || {}, {
    getComposeDefaults: '{{ URL::to("/get-compose-defaults") }}',
    getTemplates: '{{ URL::to("/get-templates") }}'
});
window.saveComposeEmail = function() {
    if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
        tinymce.get('compose_email_message').save();
    }
    customValidate('sendmail');
};
jQuery(document).ready(function($){
	
	$('.attach_more').on('click', function(){
		var numItems = $('.attfile').length;
		if(numItems <= 4){
		$('.filesdata').append('<div class="form-group row attfile"><div class="col-sm-12"><label for="subject" class="col-form-label col-sm-2">Attachment</label><div class="col-sm-6"><input type="file" name="attachemnt[]" class="form-control"></div><div class="col-sm-4"><a href="javascript:;" class="removeatt">Remove</a></div></div></div>');
		}
		var numItemss = $('.attfile').length;
		if(numItemss <= 4){}
		else{
			$('.attachore').hide();
		}
	});
	$(document).delegate('.removeatt','click', function(){
		$(this).parent().parent().parent().remove();
		var numItems = $('.attfile').length;
		if(numItems <= 4){
			$('.attachore').show();
		}
	});
	$('.composermodel').on('click', function(){
		$('#composermodel').modal('show');
	});
	$('.followuphistory').html('<div class="alert alert-info">Followup timeline has been retired. Use Add Note or view activity from the client record after conversion.</div>');

});
$(function () {
	$(document).delegate('.clientemail', 'click', function(){
		$('#emailmodal').modal('show');
	});
  $('[data-bs-toggle="tooltip"]').tooltip();
   
   // Initialize Flatpickr for datepickers
   if (typeof flatpickr !== 'undefined') {
     $('.datepicker').each(function() {
       var $this = $(this);
       if (!$this.data('flatpickr')) {
         flatpickr(this, {
           dateFormat: 'd/m/Y', // DD/MM/YYYY format
           allowInput: true,
           clickOpens: true,
           locale: { firstDayOfWeek: 1 },
           onChange: function(selectedDates, dateStr, instance) {
             $this.val(dateStr);
             $this.trigger('change');
           }
         });
       }
     });
   }
	var crmRecipientsUrl = '{{ URL::to('/clients/get-recipients') }}';
	if (typeof initTS === 'function' && typeof buildCrmGetRecipientsMultiTomSelectConfig === 'function') {
		$('#emailmodal .js-data-example-ajaxcc').each(function () {
			initTS(this, buildCrmGetRecipientsMultiTomSelectConfig({
				url: crmRecipientsUrl,
				dropdownParent: '#emailmodal',
				enableRemoteLoad: true
			}));
		});
	}

	$(document).delegate('#emailmodal .selecttemplate', 'change', function(){
	var v = $(this).val();
	$.ajax({
		url: '{{URL::to('/get-templates')}}',
		type:'GET',
		datatype:'json',
		data:{id:v},
		success: function(response){
			var res = typeof response === 'string' ? JSON.parse(response) : response;
			$('.selectedsubject').val(res.subject);
			 // Clear and set TinyMCE editor content
                    $("#emailmodal .tinymce-editor").each(function() {
                        var editorId = $(this).attr('id');
                        if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                            tinymce.get(editorId).setContent(res.description || '');
                        } else {
                            $(this).val(res.description || '');
                        }
                    }); 
			
		}
	});
});

	function refreshLeadComposeAttachments() {
		var matterId = $('#compose_client_matter_select_lead').val() || $('#compose_client_matter_id').val();
		$('#compose_client_matter_id').val(matterId || '');
		var params = { client_id: {{ (int) $fetchedData->id }} };
		if (matterId) {
			params.client_matter_id = matterId;
		}
		if (typeof window.loadComposeMatterDocuments === 'function') {
			window.loadComposeMatterDocuments(params);
		}
	}
	$('#compose_client_matter_select_lead').on('change', refreshLeadComposeAttachments);
	$('#emailmodal').on('shown.bs.modal', refreshLeadComposeAttachments);

$(document).delegate('.opennotepopup', 'click', function(){
		var notename = $.trim($(this).attr('data-notename'));
			var notetype = $.trim($(this).attr('data-notetype'));
		$('#myAddnotes .modal-title').html(notename);
		// createnote expects task_group (e.g. "Others"); data-notetype is "others"
		var taskGroup = notetype
			? (notetype.charAt(0).toUpperCase() + notetype.slice(1).toLowerCase())
			: 'Others';
		$('#myAddnotes #task_group').val(taskGroup);
		$('#myAddnotes').modal('show');
	});
});
</script>

<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form.cdn-convert-lead-to-client-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.getAttribute('data-has-assigned-matter') !== '1') {
                    e.preventDefault();
                    alert('A matter must be assigned before converting this lead to a client. You will be taken to the edit page to assign a matter.');
                    var url = form.getAttribute('data-edit-url');
                    if (url) {
                        window.location.href = url;
                    }
                    return false;
                }
                if (!window.confirm('Are you sure you want to convert this lead to a client?')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    });
})();
</script>
@endpush