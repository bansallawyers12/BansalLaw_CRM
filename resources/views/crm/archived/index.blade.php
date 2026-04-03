@extends('layouts.crm_client_detail')
@section('title', 'Clients Archived')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Page-specific styles for archived page */
    /* Fix dropdown menu display for action buttons */
    .listing-container .dropdown-menu {
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
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 1px solid rgba(102, 126, 234, 0.2);
        border-radius: 8px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        background-clip: padding-box;
        z-index: 9999 !important;
        transform: none !important;
        max-height: none !important;
        overflow: visible !important;
        backdrop-filter: blur(10px);
    }
    
    .listing-container .dropdown-menu.show {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .listing-container .table td .dropdown {
        position: relative;
        display: inline-block;
    }
    
    .listing-container .table td .dropdown-toggle {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: 1px solid #667eea !important;
        min-width: 80px;
        max-width: 90px;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 500;
        color: white !important;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    
    .listing-container .table td .dropdown-toggle:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%) !important;
        border-color: #5a6fd8 !important;
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    }
    
    .listing-container .table td .dropdown-toggle:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
    }
    
    .listing-container .table td .dropdown-toggle::after {
        content: '';
        display: inline-block;
        margin-left: 4px;
        vertical-align: middle;
        border-top: 4px solid;
        border-right: 4px solid transparent;
        border-bottom: 0;
        border-left: 4px solid transparent;
    }
    
    .listing-container .table td .dropdown-toggle.show::after {
        transform: rotate(180deg);
    }
    
    .listing-container .dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 20px;
        clear: both;
        font-weight: 500;
        color: #495057;
        text-align: inherit;
        white-space: nowrap;
        background-color: transparent !important;
        border: 0;
        text-decoration: none;
        border-radius: 4px;
        margin: 2px 8px;
        width: calc(100% - 16px);
    }
    
    .listing-container .dropdown-item:hover,
    .listing-container .dropdown-item:focus {
        color: #667eea !important;
        text-decoration: none !important;
        background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%) !important;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    }
    
    .listing-container .dropdown-item:active {
        color: #667eea !important;
        background: linear-gradient(135deg, #e8ecff 0%, #d8e0ff 100%) !important;
        text-decoration: none !important;
    }
    
    .listing-container .dropdown-item.has-icon {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .listing-container .dropdown-item.has-icon i {
        width: 16px;
        text-align: center;
    }
    
    /* Ensure all dropdown items are visible */
    .listing-container .dropdown-menu .dropdown-item {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        height: auto !important;
        min-height: 32px !important;
        line-height: 1.5 !important;
        background-color: transparent !important;
    }
    
    /* Override Bootstrap default white background on hover */
    .listing-container .dropdown-menu .dropdown-item:hover,
    .listing-container .dropdown-menu .dropdown-item:focus {
        color: #667eea !important;
        background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%) !important;
        text-decoration: none !important;
    }
    
    .listing-container .dropdown-menu .dropdown-item:active {
        color: #667eea !important;
        background: linear-gradient(135deg, #e8ecff 0%, #d8e0ff 100%) !important;
        text-decoration: none !important;
    }
    
    /* Remove any potential overflow restrictions */
    .listing-container .table td {
        overflow: visible !important;
    }
    
    .listing-container .table td .dropdown {
        overflow: visible !important;
    }
    
    /* Ensure dropdown container doesn't clip content */
    .listing-container .table {
        overflow: visible !important;
    }
    
    .listing-container .table-responsive {
        overflow: visible !important;
    }
    
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg">
                </div>
                <div class="card-header">
                    <h4>All Clients Archived</h4>
                </div>

                <div class="card-body">
                    <ul class="nav nav-pills" id="client_tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link " id="clients-tab"  href="{{URL::to('/clients')}}" >Clients</a>
                        </li>
                        <li class="nav-item ">
                            <a class="nav-link active" id="archived-tab"  href="{{URL::to('/archived')}}" >Archived</a>
                        </li>
                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link" id="lead-tab"  href="{{URL::to('/leads')}}" >Leads</a>
                        </li>
                    </ul>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-center">
                                        <div class="custom-checkbox custom-checkbox-table custom-control">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="custom-control-input" id="checkbox-all">
                                            <label for="checkbox-all" class="custom-control-label">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th>Name</th>
                                    <th>Agent</th>
                                    <th>Tag(s)</th>
                                    <th>Current City</th>
                                    <th>Assignee</th>
                                    <th>Archived By</th>
                                    <th>Archived On</th>
                                    <th>Added On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                <?php $i=0; ?>
                                    @foreach (@$lists as $list)
                                    <tr id="id_{{$list->id}}">
                                        <td style="white-space: initial;" class="text-center">
                                            <div class="custom-checkbox custom-control">
                                                <input type="checkbox" data-checkboxes="mygroup" class="custom-control-input" id="checkbox-{{$i}}">
                                                <label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>
                                            </div>
                                        </td>
                                        <td style="white-space: initial;"> {{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }} {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }}</td>
                                        <?php
                                        $agent = \App\Models\AgentDetails::where('id', $list->agent_id)->first();
                                        ?>
                                        <td style="white-space: initial;">@if($agent) {{ @$agent->full_name }} @else - @endif</td>
                                        <td style="white-space: initial;">-</td>
                                        <td style="white-space: initial;">{{@$list->city}}</td>
                                        <?php
                                        $assignee = \App\Models\Staff::where('id',@$list->assignee)->first();
                                        ?>
                                        <td style="white-space: initial;">{{ @$assignee->first_name == "" ? config('constants.empty') : Str::limit(@$assignee->first_name, '50', '...') }}</td>
                                        <td style="white-space: initial;">{{date('d/m/Y', strtotime($list->archived_on))}}</td>
                                        <td style="white-space: initial;">-</td>
                                        <td style="white-space: initial;">{{date('d/m/Y', strtotime($list->created_at))}}</td>
                                        <td style="white-space: initial;">
                                            <div class="dropdown d-inline">
                                                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item has-icon" href="javascript:;" onclick="movetoclientAction({{$list->id}}, 'admins','is_archived')">Move to clients</a>
                                                    <a class="dropdown-item has-icon" href="javascript:;" onclick="unarchiveClientAction({{$list->id}}, '{{ @$list->first_name }} {{ @$list->last_name }}')">
                                                        <i class="fas fa-undo"></i> Unarchive
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $i++; ?>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="10" style="text-align: center; padding: 20px;">
                                            No Record Found
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="card-footer">
                    {!! $lists->appends(\Request::except('page'))->render() !!}
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
        $('.listing-container [data-checkboxes]').each(function () {
            var me = $(this),
            group = me.data('checkboxes'),
            role = me.data('checkbox-role');

            me.change(function () {
                var all = $('.listing-container [data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"])'),
                checked = $('.listing-container [data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"]):checked'),
                dad = $('.listing-container [data-checkboxes="' + group + '"][data-checkbox-role="dad"]'),
                total = all.length,
                checked_length = checked.length;
                if (role == 'dad') {
                    if (me.is(':checked')) {
                        all.prop('checked', true);
                    } else {
                        all.prop('checked', false);
                    }
                } else {
                    if (checked_length >= total) {
                        dad.prop('checked', true);
                    } else {
                        dad.prop('checked', false);
                    }
                }
            });
        });

        $('.listing-container .cb-element').change(function () {
            if ($('.listing-container .cb-element:checked').length == $('.listing-container .cb-element').length){
                $('.listing-container #checkbox-all').prop('checked',true);
            }
            else {
                $('.listing-container #checkbox-all').prop('checked',false);
            }
        });
    });
</script>
<script>
    // Unarchive client function - similar to movetoclientAction
    function unarchiveClientAction(id, clientName) {
        var confirmMessage = 'Are you sure you want to unarchive the client "' + clientName + '"?\n\nThis will move the client back to the active clients list.';
        var conf = confirm(confirmMessage);
        
        if(conf) {
            if(id == '') {
                alert('Please select a valid client ID.');
                return false;
            } else {
                $('.popuploader').show();
                $(".server-error").html(''); //remove server error.
                $(".custom-error-msg").html(''); //remove custom error.
                
                $.ajax({
                    type: 'POST',
                    headers: { 
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: '{{ route("clients.unarchive", ":id") }}'.replace(':id', id),
                    data: {},
                    dataType: 'json',
                    success: function(resp) {
                        $('.popuploader').hide();
                        var obj = resp;
                        
                        // Handle response - check if it's already parsed or needs parsing
                        if (typeof resp === 'string') {
                            try {
                                obj = $.parseJSON(resp);
                            } catch(e) {
                                console.error('JSON parse error:', e);
                                var html = errorMessage('Invalid server response. Please try again.');
                                $(".custom-error-msg").html(html);
                                $('html, body').animate({scrollTop:0}, 'slow');
                                return;
                            }
                        }
                        
                        if(obj.status == 1) {
                            // Remove the row from table
                            $("#id_"+id).fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if table is empty
                                if($('.tdata tr').length === 0) {
                                    $('.tdata').html('<tr><td colspan="10" style="text-align: center; padding: 20px;">No Record Found</td></tr>');
                                }
                            });
                            
                            // Show success message
                            var html = successMessage(obj.message || 'Client has been unarchived successfully.');
                            $(".custom-error-msg").html(html);
                        } else {
                            // Show error message even if status is 0
                            var html = errorMessage(obj.message || 'Failed to unarchive client.');
                            $(".custom-error-msg").html(html);
                        }
                        
                        $('html, body').animate({scrollTop:0}, 'slow');
                    },
                    error: function(xhr) {
                        $('.popuploader').hide();
                        var errorMessage = 'An error occurred while unarchiving the client.';
                        
                        if(xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if(xhr.responseText) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if(response.message) {
                                    errorMessage = response.message;
                                }
                            } catch(e) {
                                // Use default error message
                            }
                        }
                        
                        var html = errorMessage(errorMessage);
                        $(".custom-error-msg").html(html);
                        $('html, body').animate({scrollTop:0}, 'slow');
                    },
                    beforeSend: function() {
                        $("#loader").show();
                    },
                    complete: function() {
                        $("#loader").hide();
                    }
                });
            }
        }
    }
</script>
@endpush


