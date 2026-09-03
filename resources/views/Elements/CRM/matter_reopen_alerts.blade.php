{{-- Sticky matter-reopen alerts for approvers: resurfaces on every page load until reopen/cancel. --}}
@php
    $_reopenAlertStaff = Auth::user();
    $_reopenAlertService = app(\App\Services\MatterReopenNotificationService::class);
    $_pendingReopenAlerts = ($_reopenAlertStaff instanceof \App\Models\Staff)
        ? $_reopenAlertService->pendingAlertsForStaff($_reopenAlertStaff)
        : collect();

    $_pendingReopenMatterIds = $_pendingReopenAlerts->pluck('module_id')->filter()->unique()->values();
    $_pendingReopenMatters = $_pendingReopenMatterIds->isNotEmpty()
        ? \App\Models\ClientMatter::query()
            ->whereIn('id', $_pendingReopenMatterIds)
            ->get(['id', 'reopen_requested_by', 'client_unique_matter_no'])
            ->keyBy('id')
        : collect();
    $_pendingReopenRequesterIds = $_pendingReopenMatters->pluck('reopen_requested_by')->filter()->unique()->values();
    $_pendingReopenRequesters = $_pendingReopenRequesterIds->isNotEmpty()
        ? \App\Models\Staff::query()
            ->whereIn('id', $_pendingReopenRequesterIds)
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id')
        : collect();
@endphp

@if($_pendingReopenAlerts->isNotEmpty())
@php
    $_firstAlert = $_pendingReopenAlerts->first();
    $_firstMatter = $_pendingReopenMatters->get($_firstAlert->module_id);
    $_firstRequester = $_firstMatter && $_firstMatter->reopen_requested_by
        ? $_pendingReopenRequesters->get($_firstMatter->reopen_requested_by)
        : null;
    $_firstRequesterName = $_firstRequester
        ? trim(($_firstRequester->first_name ?? '') . ' ' . ($_firstRequester->last_name ?? ''))
        : null;
    $_firstReopenUrl = $_firstAlert->url ?? route('crm.all-notifications');
    $_firstReopenUrl .= (str_contains((string) $_firstReopenUrl, '?') ? '&' : '?') . 'show_reopen=1';
    if (!empty($_firstAlert->id)) {
        $_firstReopenUrl .= '&t=' . $_firstAlert->id;
    }
@endphp
<div class="matter-reopen-urgent-bar" id="matterReopenUrgentBar" role="alert" aria-live="assertive">
    <div class="matter-reopen-urgent-bar__inner">
        <div class="matter-reopen-urgent-bar__icon" aria-hidden="true">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="matter-reopen-urgent-bar__text">
            <strong>{{ $_pendingReopenAlerts->count() }} matter reopen {{ $_pendingReopenAlerts->count() === 1 ? 'request' : 'requests' }} need your action</strong>
            <span>
                {{ $_firstAlert->message }}
                @if($_firstRequesterName)
                    <em>(requested by {{ $_firstRequesterName }})</em>
                @endif
            </span>
        </div>
        <div class="matter-reopen-urgent-bar__actions">
            <button type="button" class="matter-reopen-urgent-bar__btn" data-bs-toggle="modal" data-bs-target="#matterReopenUrgentModal">
                Review now
            </button>
            <a href="{{ $_firstReopenUrl }}" class="matter-reopen-urgent-bar__btn matter-reopen-urgent-bar__btn--solid">
                Open matter
            </a>
            @if(!empty($_firstAlert->module_id))
                <button type="button"
                        class="matter-reopen-urgent-bar__btn matter-reopen-urgent-bar__btn--solid"
                        data-crm-reopen-matter="{{ $_firstAlert->module_id }}"
                        data-matter-id="{{ $_firstAlert->module_id }}"
                        data-requester-name="{{ $_firstRequesterName ?? '' }}">
                    Reopen matter
                </button>
            @endif
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
                    These stay highlighted until you reopen the matter or the requester cancels. Use <strong>Reopen</strong> here, or open the matter to review first.
                </p>
                <ul class="matter-reopen-urgent-list">
                    @foreach($_pendingReopenAlerts as $alert)
                        @php
                            $alertMatter = $_pendingReopenMatters->get($alert->module_id);
                            $alertRequester = $alertMatter && $alertMatter->reopen_requested_by
                                ? $_pendingReopenRequesters->get($alertMatter->reopen_requested_by)
                                : null;
                            $alertRequesterName = $alertRequester
                                ? trim(($alertRequester->first_name ?? '') . ' ' . ($alertRequester->last_name ?? ''))
                                : null;
                            $alertUrl = $alert->url ?? route('crm.all-notifications');
                            $alertUrl .= (str_contains((string) $alertUrl, '?') ? '&' : '?') . 'show_reopen=1&t=' . $alert->id;
                        @endphp
                        <li>
                            <div class="matter-reopen-urgent-list__card">
                                <span class="matter-reopen-urgent-list__badge">Action required</span>
                                <span class="matter-reopen-urgent-list__msg">{{ $alert->message }}</span>
                                @if($alertRequesterName)
                                    <span class="matter-reopen-urgent-list__requester">Requested by {{ $alertRequesterName }}</span>
                                @endif
                                <small>{{ date('d M Y, h:i A', strtotime($alert->created_at)) }}</small>
                                <div class="matter-reopen-urgent-list__actions">
                                    <a href="{{ $alertUrl }}" class="btn btn-outline-secondary btn-sm">Open matter</a>
                                    <button type="button"
                                            class="btn btn-success btn-sm"
                                            data-crm-reopen-matter="{{ $alert->module_id }}"
                                            data-matter-id="{{ $alert->module_id }}"
                                            data-requester-name="{{ $alertRequesterName ?? '' }}">
                                        <i class="fa-solid fa-arrow-rotate-right"></i> Reopen
                                    </button>
                                </div>
                            </div>
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
    window.crmMatterReopenConfig = window.crmMatterReopenConfig || {
        url: @json(route('clients.matter.reopen')),
        csrfToken: @json(csrf_token())
    };
</script>
<script src="{{ asset('js/crm/clients/matter-reopen-actions.js') }}?v={{ @filemtime(public_path('js/crm/clients/matter-reopen-actions.js')) ?: time() }}"></script>
<script>
(function () {
    function showMatterReopenModal() {
        try {
            var params = new URLSearchParams(window.location.search || '');
            if (params.get('show_reopen') === '1') {
                return; // detail page will prompt reopen instead
            }
        } catch (e) {}
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
