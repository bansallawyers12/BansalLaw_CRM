@extends('layouts.crm_client_detail')
@section('title', 'Send SMS')

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
                            <h4><i class="fas fa-paper-plane"></i> Send SMS</h4>
                        </div>
                        <div class="card-body">
                            <form id="smsForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone">Phone Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="phone" name="phone" placeholder="+61412345678" required>
                                            <small class="form-text text-muted">Include country code (e.g., +61 for Australia)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="template_id">Use Template (Optional)</label>
                                            <select class="form-control" id="template_id" name="template_id">
                                                <option value="">Select a template...</option>
                                                <!-- Templates will be loaded via AJAX -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="Enter your message here..." required></textarea>
                                    <small class="form-text text-muted">
                                        <span id="charCount">0</span>/1600 characters
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary" id="sendBtn">
                                        <i class="fas fa-paper-plane"></i> Send SMS
                                    </button>
                                    <a href="{{ route('adminconsole.features.sms.dashboard') }}" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Load templates
    loadTemplates();
    
    // Character count
    $('#message').on('input', function() {
        $('#charCount').text($(this).val().length);
    });
    
    // Template selection
    $('#template_id').on('change', function() {
        if ($(this).val()) {
            loadTemplateContent($(this).val());
        }
    });
    
    // Form submission
    $('#smsForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            phone: $('#phone').val(),
            message: $('#message').val(),
            template_id: $('#template_id').val(),
            _token: $('input[name="_token"]').val()
        };
        
        $('#sendBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        
        $.ajax({
            url: '{{ route("adminconsole.features.sms.send") }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('SMS sent successfully!');
                    $('#smsForm')[0].reset();
                    $('#charCount').text('0');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                if (response && response.message) {
                    alert('Error: ' + response.message);
                } else {
                    alert('An error occurred while sending SMS');
                }
            },
            complete: function() {
                $('#sendBtn').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send SMS');
            }
        });
    });
    
    function loadTemplates() {
        $.ajax({
            url: '{{ route("adminconsole.features.sms.templates.active") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const select = $('#template_id');
                    response.data.forEach(function(template) {
                        select.append(`<option value="${template.id}">${template.title}</option>`);
                    });
                }
            }
        });
    }
    
    function loadTemplateContent(templateId) {
        $.ajax({
            url: `/adminconsole/features/sms/templates/${templateId}`,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    $('#message').val(response.data.message);
                    $('#charCount').text(response.data.message.length);
                }
            }
        });
    }
});
</script>
@endsection
