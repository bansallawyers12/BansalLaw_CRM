@extends('layouts.crm_client_detail')
@section('title', 'Journal Receipt List')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    .listing-container {
        background: var(--page-bg, #f0f6ff);
        min-height: 100vh;
    }

    .listing-section {
        padding-top: 24px !important;
    }

    .listing-container .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 1px 4px rgba(30, 61, 96, 0.08);
        overflow: hidden;
        background: var(--card-bg, #fff);
    }

    .listing-container .card-header {
        background: linear-gradient(135deg, var(--navy) 0%, var(--sidebar-active) 100%);
        padding: 24px 32px;
        border-bottom: 3px solid var(--accent-gold, #c8992a);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .listing-container .card-header h4 {
        color: #fff !important;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.02em;
        flex: 1;
    }

    .listing-container .btn {
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        transition: filter 0.2s ease, box-shadow 0.2s ease;
    }

    .listing-container .btn-theme {
        background: rgba(255, 255, 255, 0.2) !important;
        color: #fff !important;
        backdrop-filter: blur(10px);
        border: none !important;
    }

    .listing-container .btn-theme:hover {
        filter: brightness(1.08);
        color: #fff !important;
    }

    .listing-container .card-header .btn-primary {
        background: var(--accent-gold, #c8992a) !important;
        border: 1px solid var(--accent-gold, #c8992a) !important;
        color: #fff !important;
    }

    .listing-container .card-header .btn-primary:hover {
        filter: brightness(1.06);
        box-shadow: 0 4px 12px rgba(200, 153, 42, 0.35);
    }

    .listing-container .Validate_Receipt {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .listing-container .filter_panel {
        background: var(--page-bg, #f0f6ff);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid var(--border, #c8dcef);
    }

    .listing-container .filter_panel h4 {
        color: var(--navy, #1e3d60) !important;
        font-size: 1.125rem;
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--accent-gold, #c8992a);
        display: inline-block;
    }

    .listing-container .form-group label {
        color: var(--text-muted, #5e7a90) !important;
        font-weight: 600;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 8px;
    }

    .listing-container .form-control {
        border: 1px solid var(--border, #c8dcef);
        border-radius: 10px;
        padding: 10px 16px;
        font-size: 14px;
        background: var(--card-bg, #fff);
    }

    .listing-container .form-control:focus {
        border-color: var(--sidebar-active, #3a6fa8);
        box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
        outline: none;
    }

    .listing-container .filter-buttons-container {
        margin-top: 20px;
    }

    .listing-container .filter-buttons-container .btn-primary {
        background: var(--navy, #1e3d60) !important;
        border: 1px solid var(--navy, #1e3d60) !important;
        color: #fff !important;
    }

    .listing-container .filter-buttons-container .btn-primary:hover {
        filter: brightness(1.08);
    }

    .listing-container .btn-info {
        background: var(--card-bg, #fff) !important;
        color: var(--navy, #1e3d60) !important;
        border: 1px solid var(--border, #c8dcef) !important;
        box-shadow: none;
    }

    .listing-container .btn-info:hover {
        background: var(--sidebar-hover, #c8dcef) !important;
        color: var(--navy, #1e3d60) !important;
    }

    .listing-container .card-body {
        padding: 32px;
    }

    .listing-container .table-responsive {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 0 0 1px var(--border, #c8dcef);
    }

    .listing-container .table thead {
        background: var(--page-bg, #f0f6ff) !important;
    }

    .listing-container .table thead th {
        border: none;
        padding: 14px 16px;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--navy, #1e3d60) !important;
        white-space: nowrap;
        border-bottom: 2px solid var(--border, #c8dcef);
    }

    .listing-container .table tbody tr {
        border-bottom: 1px solid var(--border, #c8dcef);
        transition: background 0.15s ease;
    }

    .listing-container .table tbody tr:hover td {
        background: #ebf3ff !important;
    }

    .listing-container .table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        border: none;
        color: var(--text-dark, #1a2c40) !important;
    }

    .listing-container .table tbody td.journal-check-cell {
        white-space: normal !important;
    }

    .listing-container .modern-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 1px solid transparent;
    }

    .listing-container .modern-badge.badge-success {
        background: rgba(30, 122, 82, 0.12);
        color: var(--success, #1e7a52);
        border-color: rgba(30, 122, 82, 0.2);
    }

    .listing-container .modern-badge.badge-danger {
        background: rgba(168, 48, 32, 0.1);
        color: var(--danger, #a83020);
        border-color: rgba(168, 48, 32, 0.2);
    }

    .listing-container .card-header .fas.fa-check-circle,
    .listing-container .fas.fa-check-circle {
        color: var(--success, #1e7a52);
    }

    .listing-container .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
        background: var(--sidebar-active, #3a6fa8);
        border-color: var(--sidebar-active, #3a6fa8);
    }

    .listing-container .custom-error-msg {
        border-radius: 12px;
        margin: 0 32px 20px;
        padding: 16px 20px;
        font-weight: 600;
        display: none;
    }

    .listing-container .custom-error-msg.alert-success {
        background: rgba(30, 122, 82, 0.1);
        color: var(--success, #1e7a52);
        border: 1px solid rgba(30, 122, 82, 0.35);
    }

    .listing-container .custom-error-msg.alert-danger {
        background: rgba(168, 48, 32, 0.08);
        color: var(--danger, #a83020);
        border: 1px solid var(--danger, #a83020);
    }

    .listing-container .card-footer {
        background: var(--page-bg, #f0f6ff);
        border-top: 1px solid var(--border, #c8dcef);
        padding: 20px 32px;
        border-radius: 0 0 16px 16px;
    }

    .listing-container .table tbody td[colspan] {
        padding: 48px 20px !important;
        text-align: center;
    }

    .listing-container .listing-empty-inner {
        color: var(--text-muted, #5e7a90);
    }

    .listing-container .listing-empty-inner > i {
        font-size: 3rem;
        opacity: 0.35;
        color: var(--sidebar-active, #3a6fa8);
        margin-bottom: 16px;
        display: block;
    }

    .listing-container .listing-empty-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-dark, #1a2c40);
    }

    .listing-container .listing-empty-hint {
        font-size: 0.875rem;
        margin-top: 8px;
        color: var(--text-muted, #5e7a90);
    }

    .listing-container .table tbody td[id^="deposit_"] {
        font-weight: 700;
        color: var(--success, #1e7a52) !important;
        font-family: 'Courier New', monospace;
    }

    @media (max-width: 768px) {
        .listing-container .card-header {
            padding: 20px;
        }

        .listing-container .card-header h4 {
            font-size: 1.25rem;
            width: 100%;
        }

        .listing-container .card-body {
            padding: 20px;
        }

        .listing-container .filter_panel {
            padding: 20px;
        }

        .listing-container .table {
            font-size: 12px;
        }

        .listing-container .table thead th,
        .listing-container .table tbody td {
            padding: 12px 10px;
        }

        .listing-container .Validate_Receipt {
            width: 100%;
            justify-content: center;
        }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .listing-container .filter_panel {
        animation: slideDown 0.3s ease;
    }

    .listing-container .sortable-header {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 30px !important;
        transition: background 0.15s ease;
    }

    .listing-container .sortable-header:hover {
        background: rgba(30, 61, 96, 0.06);
        color: var(--navy, #1e3d60) !important;
    }

    .listing-container .sort-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        display: inline-flex;
        flex-direction: column;
        gap: 2px;
        opacity: 0.3;
        transition: opacity 0.15s ease;
    }

    .listing-container .sortable-header:hover .sort-icon {
        opacity: 0.65;
    }

    .listing-container .sort-icon i {
        font-size: 8px;
        line-height: 1;
        color: var(--text-muted, #5e7a90);
    }

    .listing-container .sortable-header.sort-asc .sort-icon {
        opacity: 1;
    }

    .listing-container .sortable-header.sort-asc .sort-icon .fa-caret-up {
        color: var(--navy, #1e3d60);
        font-size: 10px;
    }

    .listing-container .sortable-header.sort-desc .sort-icon {
        opacity: 1;
    }

    .listing-container .sortable-header.sort-desc .sort-icon .fa-caret-down {
        color: var(--navy, #1e3d60);
        font-size: 10px;
    }

    @include('crm.clients.partials.enhanced-date-filter-styles')
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
                    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap" style="gap: 12px;">
                        <h4 class="mb-0" style="flex: 1 1 auto;">All Journal Receipt List</h4>
                        <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                            @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                            <a href="{{ route('clients.analytics-dashboard') }}" class="btn btn-theme btn-theme-sm" title="View Financial Analytics Dashboard"><i class="fas fa-chart-line"></i> Analytics</a>
                            @endif
                            <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn"><i class="fas fa-filter"></i> Filter</a>
                            <button type="button" class="btn btn-primary Validate_Receipt">
                                <i class="fas fa-check-circle"></i>
                                Validate Receipt
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Enhanced Date Filter Panel -->
                    <div class="filter_panel">
                        <h4>
                            Search By Details
                            @if(request()->hasAny(['date_filter_type', 'from_date', 'to_date', 'financial_year']))
                                <span class="active-filters-badge">
                                    <i class="fas fa-filter"></i>
                                    {{ collect([request('date_filter_type'), request('from_date'), request('to_date'), request('financial_year')])->filter()->count() }} Active
                                </span>
                            @endif
                        </h4>
                        <form action="{{URL::to('/clients/journalreceiptlist')}}" method="get" id="filterForm">
                            <!-- Enhanced Date Filter -->
                            @include('crm.clients.partials.enhanced-date-filter')

                            <!-- Action Buttons -->
                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                        <a class="btn btn-info" href="{{URL::to('/clients/journalreceiptlist')}}">
                                            <i class="fas fa-redo"></i> Reset All
                                        </a>
                                        @if(request()->hasAny(['date_filter_type', 'from_date', 'to_date', 'financial_year']))
                                            <button type="button" class="clear-filter-btn ml-2" id="clearDateFilters">
                                                <i class="fas fa-times-circle"></i> Clear Date Filters
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-center">
                                        <div class="custom-checkbox custom-checkbox-table custom-control">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="custom-control-input" id="checkbox-all">
                                            <label for="checkbox-all" class="custom-control-label"></label>
                                        </div>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'receipt_id' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="receipt_id">
                                        SNo.
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'client_id' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="client_id">
                                        Client Id
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'name' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="name">
                                        Name
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'trans_date' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="trans_date">
                                        Trans. Date
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'entry_date' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="entry_date">
                                        Entry Date
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'trans_no' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="trans_no">
                                        Trans. No
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'invoice_no' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="invoice_no">
                                        Invoice No
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'amount' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="amount">
                                        Amount
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'validate_receipt' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="validate_receipt">
                                        Receipt Validate
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'validated_by' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="validated_by">
                                        Validate By
                                        <span class="sort-icon">
                                            <i class="fas fa-caret-up"></i>
                                            <i class="fas fa-caret-down"></i>
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                <?php $i=0; ?>
                                    @foreach (@$lists as $list)
                                        <?php
                                        $client_info = \App\Models\Admin::select('id','first_name','last_name','client_id')->where('id', $list->client_id)->first();
                                        if(isset($list->voided_or_validated_by) && $list->voided_or_validated_by != ""){
                                            $validate_by = \App\Models\Staff::select('id','first_name','last_name')->where('id', $list->voided_or_validated_by)->first();
                                            $validate_by_full_name = $validate_by ? $validate_by->first_name.' '.$validate_by->last_name : 'N/A';
                                        } else {
                                            $validate_by_full_name = "-";
                                        }?>
                                        <?php
                                        $receipt_validate = ($list->validate_receipt == 1) ? 'Yes' : 'No';
                                        ?>
                                        <tr id="id_{{@$list->id}}">
                                            <td class="text-center journal-check-cell">
                                                <div class="custom-checkbox custom-control">
                                                    <input data-id="{{@$list->id}}" data-receiptid="{{@$list->receipt_id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" data-clientid="{{@$list->client_id}}" type="checkbox" data-checkboxes="mygroup" class="cb-element custom-control-input  your-checkbox" id="checkbox-{{$i}}">
                                                    <label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>
                                                </div>
                                            </td>
                                            <td><?php echo $list->receipt_id;?></td>
                                            <td><?php if(isset($client_info->client_id)) {echo $client_info->client_id;} else {echo 'N/A';}?></td>
                                            <td><?php if(isset($client_info->first_name)) { echo $client_info->first_name;} else {echo 'N/A';} ?></td><td><?php echo $list->trans_date;?></td>
                                            <td><?php echo $list->entry_date;?></td>
                                            <td><?php echo $list->trans_no;?></td>
                                            <td><?php echo $list->invoice_no;?></td>
                                            <td id="deposit_{{@$list->id}}"><?php echo "$".$list->total_withdrawal_amount;?></td>
                                            <td id="validate_{{@$list->id}}">
                                                <span class="modern-badge {{ $receipt_validate == 'Yes' ? 'badge-success' : 'badge-danger' }}">
                                                    @if($receipt_validate == 'Yes')
                                                        <i class="fas fa-check"></i>
                                                    @else
                                                        <i class="fas fa-times"></i>
                                                    @endif
                                                    {{ $receipt_validate }}
                                                </span>
                                            </td>
                                            <td id="validateby_{{@$list->id}}"><?php echo $validate_by_full_name;?></td> <!-- New field data -->
                                        </tr>
                                        <?php $i++; ?>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="11">
                                            <div class="listing-empty-inner">
                                                <i class="fas fa-inbox" aria-hidden="true"></i>
                                                <div class="listing-empty-title">No records found</div>
                                                <div class="listing-empty-hint">Try adjusting your filters to find what you are looking for</div>
                                            </div>
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
{{-- Bootstrap datepicker removed - using Flatpickr via enhanced-date-filter-scripts --}}
<script>
jQuery(document).ready(function($){
    $('.listing-container .filter_btn').on('click', function(){
        $('.listing-container .filter_panel').toggle();
    });

    // Enhanced Date Filter Scripts
    @include('crm.clients.partials.enhanced-date-filter-scripts')

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
                    $('.listing-container .is_checked_client').show();
                    $('.listing-container .is_checked_clientn').hide();
                } else {
                    dad.prop('checked', false);
                    $('.listing-container .is_checked_client').hide();
                    $('.listing-container .is_checked_clientn').show();
                }
            }

        });
    });

    var clickedReceiptIds = [];
    $(document).delegate('.listing-container .your-checkbox', 'click', function(){
        var clicked_receipt_id = $(this).data('receiptid');
        if ($(this).is(':checked')) {
            clickedReceiptIds.push(clicked_receipt_id);
        } else {
            var index2 = clickedReceiptIds.indexOf(clicked_receipt_id);
            if (index2 !== -1) {
                clickedReceiptIds.splice(index2, 1);
            }
        }
    });

    //validate receipt
    $(document).delegate('.listing-container .Validate_Receipt', 'click', function(){
        console.log('Validate Receipt clicked');
        console.log('clickedReceiptIds:', clickedReceiptIds);

        if ( clickedReceiptIds.length > 0)
        {

            var mergeStr = "Are you sure want to validate these receipt?";
            if (confirm(mergeStr)) {
                console.log('Starting AJAX request...');
                console.log('URL:', "{{URL::to('/')}}/validate_receipt");
                console.log('Data:', {clickedReceiptIds:clickedReceiptIds, receipt_type:4});
                
                $.ajax({
                    type:'post',
                    url:"{{URL::to('/')}}/validate_receipt",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    data: {clickedReceiptIds:clickedReceiptIds,receipt_type:4},
                    dataType: 'json',
                    beforeSend: function() {
                        console.log('AJAX request being sent...');
                    },
                    success: function(response){
                        console.log('AJAX Success! Response:', response);
                        console.log('Response type:', typeof response);
                        
                        // Parse response if it's a string (fallback for older jQuery versions)
                        var obj = (typeof response === 'string') ? $.parseJSON(response) : response;
                        console.log('Parsed object:', obj);
                        
                        if(!obj.status) {
                            alert('Error: ' + obj.message);
                            return;
                        }
                        
                        //location.reload(true);
                        var record_data = obj.record_data;
                        console.log('Record data:', record_data);
                        
                        $.each(record_data, function(index, subArray) {
                            console.log('Processing record:', subArray);
                            //console.log('index=='+index);
                            //console.log('subArray=='+subArray.id);
                            $('.listing-container #validate_' + subArray.id +' span')
                                .removeClass('badge-danger')
                                .addClass('modern-badge badge-success')
                                .html('<i class="fas fa-check"></i> Yes');
                            if(subArray.first_name != ""){
                                var validateby_full_name = subArray.first_name+" "+subArray.last_name;
                            } else {
                                var validateby_full_name = "-";
                            }
                            $('.listing-container #validateby_'+subArray.id).text(validateby_full_name);
                        });
                        $('.listing-container .custom-error-msg').text(obj.message);
                        $('.listing-container .custom-error-msg').show();
                        $('.listing-container .custom-error-msg').addClass('alert alert-success');
                        
                        // Clear checkboxes after successful validation
                        clickedReceiptIds = [];
                        $('.listing-container .cb-element').prop('checked', false);
                        $('.listing-container #checkbox-all').prop('checked', false);
                    },
                    error: function(xhr, status, error) {
                        console.error('=== AJAX ERROR ===');
                        console.error('Status:', status);
                        console.error('Error:', error);
                        console.error('XHR Status:', xhr.status);
                        console.error('XHR Status Text:', xhr.statusText);
                        console.error('Response Text:', xhr.responseText);
                        console.error('Response JSON:', xhr.responseJSON);
                        console.error('==================');
                        
                        var errorMessage = 'Unknown error occurred';
                        if(xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if(xhr.responseText) {
                            // Check if it's HTML
                            if(xhr.responseText.trim().startsWith('<')) {
                                console.error('Server returned HTML instead of JSON!');
                                console.error('First 500 chars:', xhr.responseText.substring(0, 500));
                                errorMessage = 'Server returned an HTML page instead of JSON. This usually means:\n' +
                                              '1. The route is not found (404)\n' +
                                              '2. Authentication failed (redirected to login)\n' +
                                              '3. Server error (500)\n\n' +
                                              'Check the console for the full HTML response.';
                            } else {
                                errorMessage = 'Server returned: ' + xhr.responseText.substring(0, 200);
                            }
                        } else {
                            errorMessage = error || 'Unknown error';
                        }
                        
                        alert('Error validating receipt: ' + errorMessage + '\n\nPlease check the browser console (F12) for more details.');
                    }
                });
            }
        } else {
            alert('Please select atleast 1 receipt.');
        }
    });


    $('.listing-container .cb-element').change(function () {
        if ($('.listing-container .cb-element:checked').length == $('.listing-container .cb-element').length){
            $('.listing-container #checkbox-all').prop('checked',true);
        } else {
            $('.listing-container #checkbox-all').prop('checked',false);
        }
    });

    // Sortable column headers
    $('.listing-container .sortable-header').on('click', function() {
        var sortBy = $(this).data('sort');
        var currentUrl = new URL(window.location.href);
        var currentSortBy = currentUrl.searchParams.get('sort_by');
        var currentSortOrder = currentUrl.searchParams.get('sort_order');
        
        // Determine new sort order
        var newSortOrder = 'asc';
        if (currentSortBy === sortBy && currentSortOrder === 'asc') {
            newSortOrder = 'desc';
        }
        
        // Set sort parameters
        currentUrl.searchParams.set('sort_by', sortBy);
        currentUrl.searchParams.set('sort_order', newSortOrder);
        
        // Redirect to new URL
        window.location.href = currentUrl.toString();
    });
});
</script>
@endpush
