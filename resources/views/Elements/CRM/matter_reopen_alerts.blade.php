{{-- Sticky matter-reopen alerts for approvers: resurfaces on every page load until reopen/cancel. --}}
@php
    $_reopenAlertStaff = Auth::user();
    $_reopenAlertService = app(\App\Services\MatterReopenNotificationService::class);
    $_pendingReopenAlerts = ($_reopenAlertStaff instanceof \App\Models\Staff)
        ? $_reopenAlertService->pendingAlertsForStaff($_reopenAlertStaff)
        : collect();
@endphp

@if($_pendingReopenAlerts->isNotEmpty())
<div class="matter-reopen-urgent-bar" id="matterReopenUrgentBar" role="alert" aria-live="assertive">
    <div class="matter-reopen-urgent-bar__inner">
        <div class="matter-reopen-urgent-bar__icon" aria-hidden="true">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="matter-reopen-urgent-bar__text">
            <strong>{{ $_pendingReopenAlerts->count() }} matter reopen {{ $_pendingReopenAlerts->count() === 1 ? 'request' : 'requests' }} need your action</strong>
            <span>{{ $_pendingReopenAlerts->first()->message }}</span>
        </div>
        <div class="matter-reopen-urgent-bar__actions">
            <button type="button" class="matter-reopen-urgent-bar__btn" data-bs-toggle="modal" data-bs-target="#matterReopenUrgentModal">
                Review now
            </button>
            @php
                $_firstReopenUrl = $_pendingReopenAlerts->first()->url ?? route('crm.all-notifications');
                $_firstReopenId = $_pendingReopenAlerts->first()->id ?? null;
                if ($_firstReopenId) {
                    $_firstReopenUrl .= (str_contains((string) $_firstReopenUrl, '?') ? '&' : '?') . 't=' . $_firstReopenId;
                }
            @endphp
            <a href="{{ $_firstReopenUrl }}" class="matter-reopen-urgent-bar__btn matter-reopen-urgent-bar__btn--solid">Open matter</a>
        </div>
    </div>
</div>

<div class="modal fade" id="matterReopenUrgentModal" tabindex="-1" aria-labelledby="matterReopenUrgentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content matter-reopen-urgent-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="matterReopenUrgentModalLabel">
                    <i class="fa-solid fa-folder-open me-2" aria-hidden="true"></i>
                    Matter reopen requests
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close for this page"></button>
            </div>
            <div class="modal-body">
                <p class="matter-reopen-urgent-modal__hint">
                    These stay highlighted until you reopen the matter or the requester cancels. Closing this dialog only hides it until you navigate again.
                </p>
                <ul class="matter-reopen-urgent-list">
                    @foreach($_pendingReopenAlerts as $alert)
                        @php
                            $alertUrl = $alert->url ?? route('crm.all-notifications');
                            $alertUrl .= (str_contains((string) $alertUrl, '?') ? '&' : '?') . 't=' . $alert->id;
                        @endphp
                        <li>
                            <a href="{{ $alertUrl }}">
                                <span class="matter-reopen-urgent-list__badge">Action required</span>
                                <span class="matter-reopen-urgent-list__msg">{{ $alert->message }}</span>
                                <small>{{ date('d M Y, h:i A', strtotime($alert->created_at)) }}</small>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="modal-footer">
                <a href="{{ route('crm.all-notifications') }}" class="btn btn-outline-secondary btn-sm">All notifications</a>
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Remind me on next page</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function showMatterReopenModal() {
        var el = document.getElementById('matterReopenUrgentModal');
        if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }
        bootstrap.Modal.getOrCreateInstance(el).show();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(showMatterReopenModal, 400);
        });
    } else {
        setTimeout(showMatterReopenModal, 400);
    }
})();
</script>
@endif
