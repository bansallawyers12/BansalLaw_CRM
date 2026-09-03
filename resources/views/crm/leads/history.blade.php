@extends('layouts.crm_client_detail')
@include('components.require-tinymce')
@section('title', 'Leads')

@php
	$assignee = \App\Models\Staff::where('id', @$fetchedData->user_id)->first();
	$leadFullName = trim(($fetchedData->first_name ?? '') . ' ' . ($fetchedData->last_name ?? ''));
	$leadInitials = strtoupper(substr((string) ($fetchedData->first_name ?? ''), 0, 1) . substr((string) ($fetchedData->last_name ?? ''), 0, 1));
	$leadBadgeId = 'LEAD-' . str_pad((string) $fetchedData->id, 3, '0', STR_PAD_LEFT);
	$historyLeadHasAssignedMatter = \App\Models\ClientMatter::clientHasActiveAssignedMatter((int) $fetchedData->id);
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/leads/lead-history.css') }}?v={{ file_exists(public_path('css/leads/lead-history.css')) ? filemtime(public_path('css/leads/lead-history.css')) : 1 }}">
@endpush

@section('content')
<div class="main-content lead-history-page">
	<section class="section">
		<div class="section-body">
			<div class="row">
				<div class="col-md-12">
					<div class="server-error">
						@include('../Elements/flash-message')
					</div>
				</div>
			</div>
			<div class="row lead-history-layout">
				<div class="col-12 col-md-3 col-lg-3">
					<div class="lead-history-card lh-profile">
						<div class="lh-profile__hero">
							<div class="lh-profile__avatar" aria-hidden="true">{{ $leadInitials }}</div>
							<h2 class="lh-profile__name">{{ $leadFullName }}</h2>
							<span class="lh-profile__badge"><i class="fa-solid fa-ticket"></i> {{ $leadBadgeId }}</span>
							<div class="lh-profile__actions">
								<a href="javascript:;"
								   data-id="{{ @$fetchedData->id }}"
								   data-email="{{ @$fetchedData->email }}"
								   data-name="{{ $leadFullName }}"
								   class="lh-icon-btn clientemail"
								   title="Compose email"
								   aria-label="Compose email">
									<i class="fa-solid fa-envelope"></i>
								</a>
								<a href="{{ route('leads.edit', base64_encode(convert_uuencode(@$fetchedData->id))) }}"
								   class="lh-icon-btn"
								   title="Edit lead"
								   aria-label="Edit lead">
									<i class="fa-solid fa-pen-to-square"></i>
								</a>
							</div>
						</div>
						<div class="lh-profile__body">
							@if($fetchedData->phone != '')
							<div class="lh-info-row">
								<span class="lh-info-label">Phone</span>
								<span class="lh-info-value" title="{{ @$fetchedData->country_code }} {{ @$fetchedData->phone }}">{{ @$fetchedData->country_code }} {{ @$fetchedData->phone }}</span>
							</div>
							@endif
							@if($fetchedData->email != '')
							<div class="lh-info-row">
								<span class="lh-info-label">Email</span>
								<span class="lh-info-value" title="{{ @$fetchedData->email }}">{{ @$fetchedData->email }}</span>
							</div>
							@endif
							@if($fetchedData->gender != '')
							<div class="lh-info-row">
								<span class="lh-info-label">Gender</span>
								<span class="lh-info-value">{{ @$fetchedData->gender }}</span>
							</div>
							@endif
							@if($fetchedData->dob != '')
							<div class="lh-info-row">
								<span class="lh-info-label">DOB</span>
								<span class="lh-info-value">{{ date('d/m/Y', strtotime($fetchedData->dob)) }}</span>
							</div>
							@endif
							@if($fetchedData->marital_status != '')
							<div class="lh-info-row">
								<span class="lh-info-label">Marital</span>
								<span class="lh-info-value">{{ @$fetchedData->marital_status }}</span>
							</div>
							@endif
							@if($fetchedData->visa_expiry_date != '')
							<div class="lh-info-row">
								<span class="lh-info-label">Visa Exp</span>
								<span class="lh-info-value">{{ date('d/m/Y', strtotime($fetchedData->visa_expiry_date)) }}</span>
							</div>
							@endif
							<div class="lh-info-row">
								<span class="lh-info-label">Assignee</span>
								<div class="lh-info-value lh-info-value--wrap">
									@if($assignee)
									<div class="lh-assignee">
										<div class="lh-assignee__avatar">{{ substr(@$assignee->first_name, 0, 1) }}</div>
										<div class="lh-assignee__meta">
											<span class="lh-assignee__name">{{ @$assignee->first_name }}</span>
											@if(!empty($assignee->email))
											<span class="lh-assignee__email" title="{{ @$assignee->email }}">{{ @$assignee->email }}</span>
											@endif
										</div>
									</div>
									@else
									—
									@endif
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="col-12 col-md-9 col-lg-9">
					<div class="lead-history-card lh-main">
						<div class="lh-main__header">
							<h1 class="lh-main__title">Followup History</h1>
							<div class="lh-main__actions">
								<button type="button"
										class="lh-btn lh-btn--gold opennotepopup"
										data-notename="Others"
										data-notetype="others">
									<i class="fa-solid fa-plus"></i> Add Note
								</button>
								@if($fetchedData->converted == 0)
								<form method="POST"
									  action="{{ route('leads.convert_single') }}"
									  class="cdn-convert-lead-to-client-form"
									  data-has-assigned-matter="{{ $historyLeadHasAssignedMatter ? '1' : '0' }}"
									  data-edit-url="{{ route('clients.edit', base64_encode(convert_uuencode($fetchedData->id))) }}">
									@csrf
									<input type="hidden" name="lead_id" value="{{ base64_encode(convert_uuencode($fetchedData->id)) }}">
									<button type="submit" class="lh-btn lh-btn--success">
										<i class="fa-solid fa-user-check"></i> Convert To Client
									</button>
								</form>
								@endif
							</div>
						</div>

						<div class="lh-main__body">
							<ul class="nav nav-tabs lh-tabs" id="myTab" role="tablist">
								<li class="nav-item" role="presentation">
									<a class="nav-link active" id="lh-history-tab" href="#timeline" data-bs-toggle="tab" role="tab" aria-controls="timeline" aria-selected="true">
										<i class="fa-solid fa-clock-rotate-left"></i> History
									</a>
								</li>
								<li class="nav-item" role="presentation">
									<a class="nav-link" id="lh-emails-tab" href="#emails" data-bs-toggle="tab" role="tab" aria-controls="emails" aria-selected="false">
										<i class="fa-solid fa-inbox"></i> Emails
									</a>
								</li>
							</ul>
							<div class="tab-content lh-tab-content">
								<div class="tab-pane fade show active" id="timeline" role="tabpanel" aria-labelledby="lh-history-tab">
									<div class="lh-empty">
										<div class="lh-empty__icon" aria-hidden="true">
											<i class="fa-regular fa-note-sticky"></i>
										</div>
										<h3 class="lh-empty__title">Capture the next follow-up</h3>
										<p class="lh-empty__text">
											Add a note to keep this lead’s conversation moving. Notes stay with the record when you convert to a client.
										</p>
										<button type="button"
												class="lh-btn lh-btn--gold opennotepopup"
												data-notename="Others"
												data-notetype="others">
											<i class="fa-solid fa-plus"></i> Add Note
										</button>
									</div>
								</div>
								<div class="tab-pane fade" id="emails" role="tabpanel" aria-labelledby="lh-emails-tab">
									@include('crm.emails_lead')
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

<div id="myAddnotes" class="modal fade custom_modal lh-note-modal" tabindex="-1" role="dialog" aria-labelledby="lhAddNoteTitle" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header lh-note-modal__header">
				<div class="lh-note-modal__heading">
					<span class="lh-note-modal__icon" aria-hidden="true">
						<i class="fa-solid fa-note-sticky"></i>
					</span>
					<div>
						<h5 class="modal-title mb-0" id="lhAddNoteTitle">Add Note</h5>
						<p class="lh-note-modal__subtitle mb-0" id="lhAddNoteSubtitle">For {{ $leadFullName }} · {{ $leadBadgeId }}</p>
					</div>
				</div>
				<x-crm.modal-close class="lh-note-modal__close" />
			</div>
			<form action="{{ url('/create-note') }}" method="POST" name="add-note" autocomplete="off" enctype="multipart/form-data" id="addnoteform">
				@csrf
				<div class="modal-body lh-note-modal__body">
					<div class="customerror"></div>
					<input id="task_group" name="task_group" type="hidden" value="Others">
					<input type="hidden" name="vtype" value="lead">
					<input type="hidden" name="client_id" value="{{ (int) $fetchedData->id }}">
					<input type="hidden" name="lead_id" value="{{ (int) $fetchedData->id }}">

					<div class="lh-note-modal__field">
						<label for="lh_note_type" class="lh-note-modal__label">Note type</label>
						<select id="lh_note_type" class="form-control lh-note-modal__select" aria-label="Note type">
							<option value="Others" selected>Others</option>
							<option value="Call">Call</option>
							<option value="Email">Email</option>
							<option value="In-Person">In-Person</option>
							<option value="Attention">Attention</option>
						</select>
					</div>

					<div class="lh-note-modal__field">
						<label for="description" class="lh-note-modal__label">Description <span class="span_req">*</span></label>
						<div class="lh-note-modal__editor">
							<textarea id="description" name="description" class="form-control tinymce-editor" placeholder="Write your note here..." data-valid="required"></textarea>
						</div>
					</div>
				</div>
				<div class="modal-footer lh-note-modal__footer">
					<button type="button" class="lh-btn lh-btn--ghost" data-bs-dismiss="modal">Cancel</button>
					<button type="button" class="lh-btn lh-btn--navy" onclick="return customValidate('add-note');">
						<i class="fa-solid fa-floppy-disk"></i> Save Note
					</button>
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

});
$(function () {
	$(document).delegate('.clientemail', 'click', function(){
		$('#emailmodal').modal('show');
	});
	$(document).on('click', '.lh-email-empty-cta', function(){
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
		var taskGroup = notetype
			? (notetype.charAt(0).toUpperCase() + notetype.slice(1).toLowerCase())
			: (notename || 'Others');
		if (taskGroup.toLowerCase() === 'others') {
			taskGroup = 'Others';
		}
		$('#myAddnotes .modal-title').text('Add Note');
		$('#myAddnotes #lhAddNoteSubtitle').text('For {{ $leadFullName }} · {{ $leadBadgeId }}');
		$('#myAddnotes #task_group').val(taskGroup);
		$('#myAddnotes #lh_note_type').val(taskGroup);
		$('#myAddnotes').modal('show');
	});

	$(document).on('change', '#lh_note_type', function () {
		$('#myAddnotes #task_group').val($(this).val() || 'Others');
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
                    crmAlert('A matter must be assigned before converting this lead to a client. You will be taken to the edit page to assign a matter.');
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
