@extends('layouts.crm_client_detail')
@section('title', 'Clients Archived')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
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
                    <h4>Clients Archived</h4>
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
                                        $agent = \Illuminate\Support\Facades\Schema::hasTable('agent_details') && ! empty($list->agent_id)
                                            ? \App\Models\AgentDetails::where('id', $list->agent_id)->first()
                                            : null;
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
                                                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-haspopup="true" aria-expanded="false">Action</button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item has-icon" href="javascript:;" onclick="movetoclientAction({{$list->id}}, 'admins','is_archived')"><i class="fas fa-arrow-right" aria-hidden="true"></i> Move to clients</a>
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


