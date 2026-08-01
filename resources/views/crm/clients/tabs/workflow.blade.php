{{-- Workflow tab: matter stages, deadlines, discontinue/reopen (server-rendered + JS) --}}
@php
    $workflowInModal = $workflowInModal ?? false;
@endphp
<div class="tab-pane {{ $workflowInModal ? 'workflow-in-modal' : '' }}" id="workflow-tab">
    <div class="card full-width workflow-tab-container">
        <?php
        // Selected matter from URL matter ref or latest active matter for this client
        $workflowSelectedMatter = null;
        $workflowMatterName = '';
        $workflowMatterNumber = '';

        if (isset($id1) && $id1 != "") {
            $workflowSelectedMatter = DB::table('client_matters as cm')
                ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                ->where('cm.client_id', $fetchedData->id)
                ->where('cm.client_unique_matter_no', $id1)
                ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_stage_id', 'cm.workflow_id', 'cm.matter_status', 'cm.deadline', 'cm.sel_legal_practitioner', 'cm.reopen_requested_by')
                ->first();
        } else {
            $workflowSelectedMatter = DB::table('client_matters as cm')
                ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                ->where('cm.client_id', $fetchedData->id)
                ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_stage_id', 'cm.workflow_id', 'cm.matter_status', 'cm.deadline', 'cm.sel_legal_practitioner', 'cm.reopen_requested_by')
                ->orderBy('cm.id', 'desc')
                ->first();
        }

        if ($workflowSelectedMatter) {
            $workflowMatterName = \App\Models\Matter::displayTitleFromJoinedRow($workflowSelectedMatter->title ?? null);
            $workflowMatterNumber = $workflowSelectedMatter->client_unique_matter_no;
            $workflowCurrentStageId = $workflowSelectedMatter->workflow_stage_id;
        } else {
            $workflowCurrentStageId = null;
        }

        $workflowId = $workflowSelectedMatter ? ($workflowSelectedMatter->workflow_id ?? null) : null;
        $workflowAllStages = $workflowId
            ? DB::table('workflow_stages')->where('workflow_id', $workflowId)->orderByRaw('COALESCE(sort_order, id) ASC')->get()
            : DB::table('workflow_stages')->orderByRaw('COALESCE(sort_order, id) ASC')->get();

        $workflowCurrentStageName = null;
        $workflowIsVerificationStage = false;
        $workflowCanVerifyAndProceed = false;
        if ($workflowSelectedMatter && $workflowCurrentStageId && $workflowAllStages->count() > 0) {
            $currentStageRow = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
            $workflowCurrentStageName = $currentStageRow ? $currentStageRow->name : null;
            $verificationStageNames = ['payment verified', 'verification: payment, service agreement, forms'];
            $workflowIsVerificationStage = $workflowCurrentStageName && in_array(strtolower(trim($workflowCurrentStageName)), $verificationStageNames);
            $currentUserRole = (int) (Auth::guard('admin')->user()->role ?? 0);
            $workflowCanVerifyAndProceed = in_array($currentUserRole, [1, 16]); // Admin (1) or Legal Practitioner (16)
        }
        ?>

        @if($workflowSelectedMatter)
            @php
                $workflowTotalStages = $workflowAllStages->count();
                $workflowCurrentStageRow = $workflowCurrentStageId ? $workflowAllStages->firstWhere('id', $workflowCurrentStageId) : null;
                $workflowCurrentSortVal = $workflowCurrentStageRow ? ($workflowCurrentStageRow->sort_order ?? $workflowCurrentStageRow->id) : null;
                $workflowCompletedStages = ($workflowCurrentSortVal !== null && $workflowTotalStages > 0)
                    ? $workflowAllStages->where(fn($s) => ($s->sort_order ?? $s->id) < $workflowCurrentSortVal)->count()
                    : 0;
                $workflowStagePosition = ($workflowCurrentSortVal !== null && $workflowTotalStages > 0)
                    ? $workflowAllStages->where(fn($s) => ($s->sort_order ?? $s->id) <= $workflowCurrentSortVal)->count()
                    : 0;
                if ($workflowTotalStages <= 1) {
                    $workflowProgressPercentage = 0;
                } elseif ($workflowCurrentSortVal === null) {
                    $workflowProgressPercentage = 0;
                } else {
                    $workflowProgressPercentage = (int) round(($workflowCompletedStages / max($workflowTotalStages - 1, 1)) * 100);
                }

                $workflowViewer = Auth::guard('admin')->user();
                $workflowIsDiscontinued = ($workflowSelectedMatter->matter_status ?? 1) == 0;
                $workflowCanReopen = ($workflowViewer instanceof \App\Models\Staff && ($workflowViewer->hasEffectiveSuperAdminPrivileges() || $workflowViewer->hasCrmModule('45')));
                $workflowCanDiscontinue = ($workflowViewer instanceof \App\Models\Staff && $workflowViewer->canCloseDiscontinueMatter());
                $workflowIsReadOnly = !empty($isClosedMatterView);

                $workflowIsFirstStage = false;
                $workflowNextStageName = null;
                $workflowNextStage = null;
                if ($workflowCurrentStageId && $workflowAllStages->count() > 0) {
                    $workflowFirstStage = $workflowAllStages->first();
                    $workflowIsFirstStage = ($workflowCurrentStageId == $workflowFirstStage->id);
                    $workflowCurrentOrder = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
                    $workflowCurrentSort = $workflowCurrentOrder ? ($workflowCurrentOrder->sort_order ?? $workflowCurrentOrder->id) : null;
                    $workflowNextStage = $workflowCurrentSort !== null ? $workflowAllStages->first(fn($s) => ($s->sort_order ?? $s->id) > $workflowCurrentSort) : $workflowAllStages->where('id', '>', $workflowCurrentStageId)->first();
                    $workflowNextStageName = $workflowNextStage ? $workflowNextStage->name : null;
                }
                $workflowIsLastStage = $workflowNextStage === null;
                $workflowNextBtnDisabled = $workflowIsLastStage;
                $workflowNextBtnTitle = 'Proceed to Next Stage';
                if ($workflowIsVerificationStage && !$workflowCanVerifyAndProceed) {
                    $workflowNextBtnDisabled = true;
                    $workflowNextBtnTitle = 'Only a Legal Practitioner (or Admin) can verify and proceed.';
                }
                $workflowNextBtnLabel = $workflowNextStageName ? ('Proceed to ' . $workflowNextStageName) : 'Proceed to Next Stage';
                $workflowStatusLabel = (!empty($isClosedMatterView) || (isset($workflowSelectedMatter->matter_status) && $workflowSelectedMatter->matter_status != 1))
                    ? 'Closed'
                    : ($workflowIsDiscontinued ? 'Discontinued' : 'Active');
            @endphp

            @if($workflowInModal)
                <div class="cdn-workflow-modal">
                    <div class="cdn-workflow-modal__context">
                        <div class="cdn-workflow-modal__matter">
                            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                            <span>{{ $workflowMatterName }} ({{ $workflowMatterNumber }})</span>
                        </div>
                        <span class="badge cdn-workflow-modal__status {{ $workflowStatusLabel === 'Active' ? 'bg-success' : 'bg-secondary' }}">{{ $workflowStatusLabel }}</span>
                    </div>

                    @include('crm.clients.tabs.partials.workflow-stages-list', [
                        'containerClass' => 'cdn-workflow-modal__stepper mt-3',
                        'listClass' => 'workflow-stages-list--horizontal',
                    ])

                    <div class="cdn-workflow-modal__progress mt-3">
                        <div class="progress cdn-workflow-modal__progress-bar" role="progressbar" aria-valuenow="{{ $workflowProgressPercentage }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-primary" style="width: {{ $workflowProgressPercentage }}%;"></div>
                        </div>
                        <p class="cdn-workflow-modal__progress-label mb-0">
                            @if($workflowTotalStages <= 1)
                                Stage 1 of 1
                            @else
                                {{ $workflowProgressPercentage }}% complete · Stage {{ $workflowStagePosition }} of {{ $workflowTotalStages }}
                            @endif
                        </p>
                    </div>

                    <div class="cdn-workflow-modal__current mt-3">
                        <span class="text-muted">Current stage</span>
                        <strong>{{ $workflowCurrentStageName ?? 'N/A' }}</strong>
                    </div>

                    <div class="deadline-section cdn-workflow-modal__deadline mt-3">
                        <div class="form-group mb-0">
                            @if(empty($isClosedMatterView))
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="workflow-set-deadline" data-matter-id="{{ $workflowSelectedMatter->id }}"
                                    {{ $workflowSelectedMatter->deadline ? 'checked' : '' }}>
                                <label class="form-check-label" for="workflow-set-deadline">Set deadline</label>
                            </div>
                            <div class="workflow-deadline-date-wrapper mt-2" style="{{ $workflowSelectedMatter->deadline ? '' : 'display: none;' }}">
                                <label for="workflow-deadline-date" class="sr-only">Deadline Date</label>
                                <input type="date" class="form-control form-control-sm" id="workflow-deadline-date"
                                    value="{{ $workflowSelectedMatter->deadline ? \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('Y-m-d') : '' }}"
                                    data-matter-id="{{ $workflowSelectedMatter->id }}">
                                <small class="form-text text-muted">Select the matter deadline date.</small>
                            </div>
                            @endif
                            @if($workflowSelectedMatter->deadline)
                                <div class="mt-2">
                                    <span class="badge bg-info text-dark"><i class="fa-solid fa-calendar-days"></i> Deadline: {{ \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('d/m/Y') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @include('crm.clients.tabs.partials.workflow-navigation', [
                        'navigationClass' => 'cdn-workflow-modal__actions mt-4',
                    ])
                </div>
            @else
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="info-card in-progress-section">
                        <div class="in-progress-single-line">
                            <h5 class="in-progress-title">{{ $workflowStatusLabel }}</h5>
                            <div class="current-stage-info">
                                <label class="stage-label">Current Stage:</label>
                                <div class="stage-value-container">
                                    <span class="stage-value">{{ $workflowCurrentStageName ?? 'N/A' }}</span>
                                </div>
                            </div>
                            <div class="overall-progress-container">
                                <label class="progress-label">Overall Progress:</label>
                                <div class="progress-circle-wrapper">
                                    <div class="progress-circle" data-progress="{{ $workflowProgressPercentage }}">
                                        <svg class="progress-ring" width="80" height="80">
                                            <circle class="progress-ring-circle-bg" cx="40" cy="40" r="36" fill="transparent" stroke="#e9ecef" stroke-width="6"/>
                                            <circle class="progress-ring-circle" cx="40" cy="40" r="36" fill="transparent" stroke="#007bff" stroke-width="6" stroke-dasharray="{{ 2 * M_PI * 36 }}" stroke-dashoffset="{{ 2 * M_PI * 36 * (1 - $workflowProgressPercentage / 100) }}"/>
                                        </svg>
                                        <div class="progress-text">
                                            @if($workflowTotalStages <= 1)
                                                —
                                            @else
                                                {{ $workflowProgressPercentage }}%
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="deadline-section mt-3">
                                <div class="form-group mb-0">
                                    @if(empty($isClosedMatterView))
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="workflow-set-deadline" data-matter-id="{{ $workflowSelectedMatter->id }}"
                                            {{ $workflowSelectedMatter->deadline ? 'checked' : '' }}>
                                        <label class="form-check-label" for="workflow-set-deadline">Set Deadline</label>
                                    </div>
                                    <div class="workflow-deadline-date-wrapper mt-2" style="{{ $workflowSelectedMatter->deadline ? '' : 'display: none;' }}">
                                        <label for="workflow-deadline-date" class="sr-only">Deadline Date</label>
                                        <input type="date" class="form-control form-control-sm" id="workflow-deadline-date"
                                            value="{{ $workflowSelectedMatter->deadline ? \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('Y-m-d') : '' }}"
                                            data-matter-id="{{ $workflowSelectedMatter->id }}"
                                            style="max-width: 180px;">
                                        <small class="form-text text-muted">Select the matter deadline date.</small>
                                    </div>
                                    @endif
                                    @if($workflowSelectedMatter->deadline)
                                        <div class="mt-2">
                                            <span class="badge bg-info text-dark"><i class="fa-solid fa-calendar-days"></i> Deadline: {{ \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('d/m/Y') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @include('crm.clients.tabs.partials.workflow-navigation')
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="info-card">
                        <h5>
                            <i class="fa-solid fa-folder-open"></i> {{ $workflowMatterName }} ({{ $workflowMatterNumber }})
                        </h5>

                        <div class="mt-3">
                            @include('crm.clients.tabs.partials.workflow-stages-list')
                        </div>
                    </div>
                </div>
            </div>
            @endif
        @else
            <div class="row mt-3">
                <div class="col-md-12">
                    <p class="text-muted">No matter selected. Please select a matter from the sidebar dropdown.</p>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Workflow tab: Set Deadline checkbox - toggle date picker
        var setDeadlineCb = document.getElementById('workflow-set-deadline');
        var deadlineDateWrapper = document.querySelector('.workflow-deadline-date-wrapper');
        var deadlineDateInput = document.getElementById('workflow-deadline-date');
        if (setDeadlineCb && deadlineDateWrapper && deadlineDateInput) {
            setDeadlineCb.addEventListener('change', function() {
                var checked = this.checked;
                deadlineDateWrapper.style.display = checked ? 'block' : 'none';
                if (!checked) {
                    deadlineDateInput.value = '';
                    saveMatterDeadline(this.getAttribute('data-matter-id'), false, null);
                } else if (deadlineDateInput.value) {
                    saveMatterDeadline(this.getAttribute('data-matter-id'), true, deadlineDateInput.value);
                }
            });
            deadlineDateInput.addEventListener('change', function() {
                if (!setDeadlineCb.checked) return;
                var val = this.value;
                if (val) {
                    saveMatterDeadline(this.getAttribute('data-matter-id'), true, val);
                } else {
                    setDeadlineCb.checked = false;
                    deadlineDateWrapper.style.display = 'none';
                    saveMatterDeadline(this.getAttribute('data-matter-id'), false, null);
                }
            });
        }

        function saveMatterDeadline(matterId, setDeadline, deadline) {
            if (!matterId) return;
            var payload = { matter_id: matterId, set_deadline: setDeadline };
            if (setDeadline && deadline) payload.deadline = deadline;

            fetch('{{ route("clients.matter.update-deadline") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to update deadline.');
                }
            })
            .catch(function(err) {
                console.error(err);
                alert('An error occurred.');
            });
        }

        // Workflow tab: Proceed to Next Stage
        var nextBtn = document.getElementById('workflow-tab-proceed-to-next-stage');
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                var matterId = this.getAttribute('data-matter-id');
                var nextStageName = (this.getAttribute('data-next-stage-name') || '').trim();
                var isVerificationStage = this.getAttribute('data-is-verification-stage') === '1';
                var canVerifyAndProceed = this.getAttribute('data-can-verify-and-proceed') === '1';
                if (!matterId) { alert('Error: Matter ID not found'); return; }

                // If at Verification stage (Payment, Service Agreement, Forms), Legal Practitioner must tick and add optional note
                if (isVerificationStage && canVerifyAndProceed) {
                    document.getElementById('verification-payment-forms-matter-id').value = matterId;
                    document.getElementById('verification-confirm-checkbox').checked = false;
                    document.getElementById('verification-note').value = '';
                    var errEl = document.querySelector('.verification-confirm-error strong');
                    if (errEl) errEl.textContent = '';
                    $('#verification-payment-forms-modal').modal('show');
                    return;
                }

                // If next stage is "Decision Received", show outcome modal first
                if (nextStageName && nextStageName.toLowerCase() === 'decision received') {
                    document.getElementById('decision-received-matter-id').value = matterId;
                    document.getElementById('decision-outcome').value = '';
                    document.getElementById('decision-note').value = '';
                    document.querySelector('.decision-outcome-error strong').textContent = '';
                    document.querySelector('.decision-note-error strong').textContent = '';
                    $('#decision-received-modal').modal('show');
                    return;
                }

                if (!confirm('Are you sure you want to proceed to the next stage?')) return;

                doProceedToNextStage(matterId, null, null, nextBtn);
            });
        }

        // Shared: Proceed to next stage (optional: decision_outcome/decision_note for Decision Received; verification_confirm/verification_note for Verification stage)
        function doProceedToNextStage(matterId, decisionOutcome, decisionNote, btnEl, verificationConfirm, verificationNote) {
            var btn = btnEl || document.getElementById('workflow-tab-proceed-to-next-stage');
            var orig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...'; }

            var payload = { matter_id: matterId };
            if (decisionOutcome) payload.decision_outcome = decisionOutcome;
            if (decisionNote) payload.decision_note = decisionNote;
            if (verificationConfirm !== undefined) payload.verification_confirm = verificationConfirm;
            if (verificationNote !== undefined) payload.verification_note = verificationNote;

            fetch('{{ route("clients.matter.update-next-stage") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status) {
                    alert(data.message || 'Matter has been successfully moved to the next stage.');
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to move to next stage.');
                    if (btn) { btn.disabled = false; btn.innerHTML = orig; if (data.is_last_stage) btn.disabled = true; }
                }
            })
            .catch(function(err) {
                console.error(err);
                alert('An error occurred.');
                if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            });
        }

        // Verification: Payment, Service Agreement, Forms modal — submit handlers live in this tab / shared CRM scripts.

        // Decision Received modal: submit handled by delegated handlers on the client detail page.

        // Workflow tab: Back to Previous Stage
        var prevBtn = document.getElementById('workflow-tab-back-to-previous-stage');
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                var matterId = this.getAttribute('data-matter-id');
                if (!matterId) { alert('Error: Matter ID not found'); return; }
                if (!confirm('Are you sure you want to move back to the previous stage?')) return;

                var btn = this;
                var orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

                fetch('{{ route("clients.matter.update-previous-stage") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                    body: JSON.stringify({ matter_id: matterId })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status) {
                        alert(data.message || 'Matter has been successfully moved to the previous stage.');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to move to previous stage.');
                        btn.disabled = false;
                        btn.innerHTML = orig;
                        if (data.is_first_stage) btn.disabled = true;
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    alert('An error occurred.');
                    btn.disabled = false;
                    btn.innerHTML = orig;
                });
            });
        }

        // Workflow tab: Change Workflow button - opens modal
        var changeWorkflowBtn = document.getElementById('workflow-tab-change-workflow');
        if (changeWorkflowBtn) {
            changeWorkflowBtn.addEventListener('click', function() {
                var matterId = this.getAttribute('data-matter-id');
                var currentWorkflowId = this.getAttribute('data-current-workflow-id');
                if (!matterId) { alert('Error: Matter ID not found'); return; }
                document.getElementById('change-workflow-matter-id').value = matterId;
                var select = document.getElementById('change-workflow-select');
                if (select && currentWorkflowId) {
                    select.value = currentWorkflowId;
                }
                $('#change-workflow-modal').modal('show');
            });
        }
        var changeWorkflowSubmit = document.getElementById('change-workflow-submit');
        if (changeWorkflowSubmit) {
            changeWorkflowSubmit.addEventListener('click', function() {
                var matterId = document.getElementById('change-workflow-matter-id').value;
                var workflowId = document.getElementById('change-workflow-select').value;
                if (!matterId || !workflowId) { alert('Please select a workflow.'); return; }
                var btn = this;
                var orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
                fetch('{{ route("clients.matter.change-workflow") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'Accept': 'application/json' },
                    body: JSON.stringify({ matter_id: matterId, workflow_id: workflowId })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    if (data.status) {
                        $('#change-workflow-modal').modal('hide');
                        alert(data.message || 'Workflow changed successfully.');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to change workflow.');
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    alert('An error occurred.');
                });
            });
        }

        // Workflow tab: Discontinue button - opens modal
        var discontinueBtn = document.getElementById('workflow-tab-discontinue');
        if (discontinueBtn) {
            discontinueBtn.addEventListener('click', function() {
                var matterId = this.getAttribute('data-matter-id');
                if (typeof window.openDiscontinueMatterModal === 'function') {
                    window.openDiscontinueMatterModal(matterId);
                } else if (matterId) {
                    $('#discontinue-matter-modal').modal('show');
                } else {
                    alert('Error: Matter ID not found');
                }
            });
        }

        // Reopen button (discontinued matters, workflow tab)
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.matter-detail-reopen-btn');
            if (!btn) return;
            e.preventDefault();
            var matterId = btn.getAttribute('data-matter-id');
            if (!matterId) return;
            if (!confirm('Reopen this matter? It will be moved back to active matters.')) return;
            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Reopening...';
            var currentTab = document.querySelector('.client-nav-button.active')?.getAttribute('data-tab') || '';
            fetch('{{ route("clients.matter.reopen") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'Accept': 'application/json' },
                body: JSON.stringify({ matter_id: matterId, current_tab: currentTab })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to reopen matter.');
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            })
            .catch(function() {
                alert('An error occurred. Please try again.');
                btn.disabled = false;
                btn.innerHTML = origHtml;
            });
        });

        // Request Reopen button (non-admins, workflow tab)
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.matter-detail-request-reopen-btn');
            if (!btn) return;
            e.preventDefault();
            var matterId = btn.getAttribute('data-matter-id');
            if (!matterId) return;
            if (!confirm('Send a request to the admin to reopen this matter?')) return;
            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Requesting...';
            var currentTab = document.querySelector('.client-nav-button.active')?.getAttribute('data-tab') || '';
            fetch('{{ route("clients.matter.request-reopen") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'Accept': 'application/json' },
                body: JSON.stringify({ matter_id: matterId, current_tab: currentTab })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status) {
                    alert(data.message || 'Reopen request has been sent to admins.');
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to request reopen.');
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            })
            .catch(function() {
                alert('An error occurred. Please try again.');
                btn.disabled = false;
                btn.innerHTML = origHtml;
            });
        });
    });
})();
</script>
@endpush
