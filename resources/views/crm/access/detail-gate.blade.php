@extends('layouts.crm_client_detail')
@section('title', 'Access restricted')

@section('content')
@php
    $recordLabel = ($recordType ?? 'client') === 'lead' ? 'lead' : 'client';
    $recordLabelTitle = ucfirst($recordLabel);
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="main-content">
    <section class="section">
        <div class="section-body d-flex justify-content-center align-items-center py-5">
            <div class="card shadow-sm w-100" style="max-width: 560px;">
                <div class="card-body py-5 px-4 text-center">
                    <div class="mb-3 text-secondary" style="font-size: 3rem;" aria-hidden="true">
                        <i class="fa-solid fa-user-lock"></i>
                    </div>
                    <h4 class="mb-2">You do not have permission to view this {{ $recordLabel }}</h4>
                    @if(!empty($displayName))
                        <p class="text-muted mb-3">{{ $recordLabelTitle }}: <strong>{{ $displayName }}</strong></p>
                    @endif
                    <p class="text-muted mb-4">
                        This {{ $recordLabel }} file is not assigned to you, so its details cannot be opened.
                        @if(!empty($canRequestAccess))
                            If you need to work on this file, you can request temporary access below.
                        @else
                            If you believe you should have access, please contact your supervisor or office administrator.
                        @endif
                    </p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        <a href="{{ route('clients.index') }}" class="btn btn-primary">Back to Clients</a>
                        @if(!empty($canRequestAccess))
                            <button type="button" class="btn btn-outline-primary" id="crmDetailGateRequestAccess">
                                Request access
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('crmDetailGateRequestAccess');
    var payload = @json($accessModalPayload ?? null);
    if (btn && payload && typeof window.openCrmAccessModal === 'function') {
        btn.addEventListener('click', function () {
            window.openCrmAccessModal(payload);
        });
    }
});
</script>
@endpush
