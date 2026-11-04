@extends('layouts.crm_client_detail')
@section('title', 'Access restricted')

@section('content')
@php
    $recordLabel = ($recordType ?? 'client') === 'lead' ? 'lead' : 'client';
    $recordLabelTitle = ucfirst($recordLabel);
    $showMatterDayRequest = !empty($canRequestMatterDay) && !empty($matterDayRequest);
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
                    <h4 class="mb-2">{{ !empty($gateMessage) ? 'Access to this matter is restricted' : 'You do not have permission to view this ' . $recordLabel }}</h4>
                    @if(!empty($displayName))
                        <p class="text-muted mb-3">{{ $recordLabelTitle }}: <strong>{{ $displayName }}</strong></p>
                    @endif
                    <p class="text-muted mb-4" id="matterDayRequestIntro">
                        @if($showMatterDayRequest && !empty($matterDayPending))
                            Request sent. Khushi, Ajay, or a Super Admin can approve one day of access to this matter. You will be notified when it is approved.
                        @elseif($showMatterDayRequest)
                            You are not on the matter team for this file. Request access and Khushi, Ajay, or a Super Admin can approve one day of access to this matter.
                        @elseif(!empty($gateMessage))
                            {{ $gateMessage }}
                        @else
                            This {{ $recordLabel }} file is not assigned to you, so its details cannot be opened.
                            @if(!empty($canRequestAccess))
                                If you need to work on this file, you can request temporary access below.
                            @else
                                If you believe you should have access, please contact your supervisor or office administrator.
                            @endif
                        @endif
                    </p>
                    @if($showMatterDayRequest)
                        <div id="matterDayRequestPanel" class="mx-auto mb-4 text-start" style="max-width: 320px;" @if(!empty($matterDayPending)) hidden @endif>
                            <label class="form-label small text-muted" for="matterDayReason">Reason</label>
                            <select id="matterDayReason" class="form-select form-select-sm mb-3">
                                @foreach(($matterDayRequest['reasons'] ?? []) as $reasonCode => $reasonLabel)
                                    <option value="{{ $reasonCode }}">{{ $reasonLabel }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-primary w-100" id="matterDayRequestBtn">Request access</button>
                        </div>
                        <p class="text-muted mb-4 d-none" id="matterDayRequestStatus" role="status"></p>
                    @endif
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

    var matterBtn = document.getElementById('matterDayRequestBtn');
    var matterRequest = @json($matterDayRequest ?? null);
    if (!matterBtn || !matterRequest || !matterRequest.url) {
        return;
    }
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var token = tokenEl ? tokenEl.getAttribute('content') : '';
    matterBtn.addEventListener('click', function () {
        var reasonEl = document.getElementById('matterDayReason');
        var reason = reasonEl ? reasonEl.value : '';
        matterBtn.disabled = true;
        fetch(matterRequest.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                client_matter_id: matterRequest.client_matter_id,
                reason_code: reason
            })
        }).then(function (r) {
            return r.json().then(function (j) { return { ok: r.ok, j: j }; });
        }).then(function (x) {
            var status = document.getElementById('matterDayRequestStatus');
            var panel = document.getElementById('matterDayRequestPanel');
            if (!x.ok) {
                matterBtn.disabled = false;
                if (status) {
                    status.textContent = (x.j && x.j.message) ? x.j.message : 'Could not send the request.';
                    status.classList.remove('d-none');
                    status.classList.add('text-danger');
                }
                return;
            }
            if (panel) {
                panel.hidden = true;
            }
            var intro = document.getElementById('matterDayRequestIntro');
            if (intro) {
                intro.textContent = 'Request sent. Khushi, Ajay, or a Super Admin can approve one day of access to this matter. You will be notified when it is approved.';
            }
            if (status) {
                status.textContent = '';
                status.classList.add('d-none');
                status.classList.remove('text-danger');
            }
        }).catch(function () {
            matterBtn.disabled = false;
            var status = document.getElementById('matterDayRequestStatus');
            if (status) {
                status.textContent = 'Could not send the request.';
                status.classList.remove('d-none');
                status.classList.add('text-danger');
            }
        });
    });
});
</script>
@endpush
