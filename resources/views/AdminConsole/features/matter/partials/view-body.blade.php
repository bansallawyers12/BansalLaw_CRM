@php
    $fetchedData->loadMissing('workflow');
    $streamLabel = '—';
    if (\Illuminate\Support\Facades\Schema::hasColumn('matters', 'stream') && !empty($fetchedData->stream)) {
        $streamLabel = \Illuminate\Support\Arr::get(config('matter_streams.streams', []), $fetchedData->stream, $fetchedData->stream);
    }
@endphp

<div class="mat-view-section">
    <h6 class="mat-view-section__title">Matter details</h6>
    <dl class="mat-view-dl row mb-0">
        <div class="col-md-6">
            <dt>Title</dt>
            <dd>{{ $fetchedData->title ?: '—' }}</dd>
        </div>
        <div class="col-md-6">
            <dt>Nick name</dt>
            <dd>{{ $fetchedData->nick_name ?: '—' }}</dd>
        </div>
        @if(\Illuminate\Support\Facades\Schema::hasColumn('matters', 'stream'))
        <div class="col-md-6">
            <dt>Stream / forum</dt>
            <dd>{{ $streamLabel }}</dd>
        </div>
        @endif
        <div class="col-md-6">
            <dt>Default workflow</dt>
            <dd>{{ optional($fetchedData->workflow)->name ?: 'General' }}</dd>
        </div>
        <div class="col-md-6">
            <dt>For companies</dt>
            <dd>{{ (int) ($fetchedData->is_for_company ?? 0) === 1 ? 'Yes' : 'No (personal clients)' }}</dd>
        </div>
    </dl>
</div>

@if(\Illuminate\Support\Facades\Schema::hasColumn('matters', 'Block_1_Description') && ($fetchedData->Block_1_Description || $fetchedData->Block_2_Description || $fetchedData->Block_3_Description))
<div class="mat-view-section mt-3">
    <h6 class="mat-view-section__title">Block fees</h6>
    <dl class="mat-view-dl row mb-0">
        @if($fetchedData->Block_1_Description || $fetchedData->Block_1_Ex_Tax)
        <div class="col-md-6">
            <dt>Block 1</dt>
            <dd>{{ $fetchedData->Block_1_Description ?: '—' }} @if($fetchedData->Block_1_Ex_Tax)<span class="text-muted">({{ $fetchedData->Block_1_Ex_Tax }})</span>@endif</dd>
        </div>
        @endif
        @if($fetchedData->Block_2_Description || $fetchedData->Block_2_Ex_Tax)
        <div class="col-md-6">
            <dt>Block 2</dt>
            <dd>{{ $fetchedData->Block_2_Description ?: '—' }} @if($fetchedData->Block_2_Ex_Tax)<span class="text-muted">({{ $fetchedData->Block_2_Ex_Tax }})</span>@endif</dd>
        </div>
        @endif
        @if($fetchedData->Block_3_Description || $fetchedData->Block_3_Ex_Tax)
        <div class="col-md-6">
            <dt>Block 3</dt>
            <dd>{{ $fetchedData->Block_3_Description ?: '—' }} @if($fetchedData->Block_3_Ex_Tax)<span class="text-muted">({{ $fetchedData->Block_3_Ex_Tax }})</span>@endif</dd>
        </div>
        @endif
    </dl>
</div>
@endif

@if(\Illuminate\Support\Facades\Schema::hasColumn('matters', 'additional_fee_1') && $fetchedData->additional_fee_1)
<div class="mat-view-section mt-3">
    <h6 class="mat-view-section__title">Additional fee</h6>
    <p class="mb-0">{{ $fetchedData->additional_fee_1 }}</p>
</div>
@endif
