<!-- Workflow Tab - Matter-specific, mirrors Client Portal workflow UI -->
<div class="tab-pane" id="workflow-tab">
    <div class="card full-width workflow-tab-container">
        <?php
        // Get the selected matter based on URL parameter or latest matter (same logic as Client Portal)
        $workflowSelectedMatter = null;
        $workflowMatterName = '';
        $workflowMatterNumber = '';

        if (isset($id1) && $id1 != "") {
            $workflowSelectedMatter = DB::table('client_matters as cm')
                ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                ->where('cm.client_id', $fetchedData->id)
                ->where('cm.client_unique_matter_no', $id1)
                ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_stage_id', 'cm.workflow_id', 'cm.matter_status', 'cm.deadline', 'cm.sel_migration_agent')
                ->first();
        } else {
            $workflowSelectedMatter = DB::table('client_matters as cm')
                ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                ->where('cm.client_id', $fetchedData->id)
                ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_stage_id', 'cm.workflow_id', 'cm.matter_status', 'cm.deadline', 'cm.sel_migration_agent')
                ->orderBy('cm.id', 'desc')
                ->first();
        }

        if ($workflowSelectedMatter) {
            if ($workflowSelectedMatter->sel_matter_id == 1 || empty($workflowSelectedMatter->title)) {
                $workflowMatterName = 'General Matter';
            } else {
                $workflowMatterName = $workflowSelectedMatter->title;
            }
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
            $workflowCanVerifyAndProceed = in_array($currentUserRole, [1, 16]); // Admin (1) or Migration Agent (16)
        }
        ?>

        @if($workflowSelectedMatter)
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="info-card in-progress-section">
                        <div class="in-progress-single-line">
                            <h5 class="in-progress-title">
                                @if(isset($workflowSelectedMatter->matter_status) && $workflowSelectedMatter->matter_status == 1)
                                    Active
                                @else
                                    In-active
                                @endif
                            </h5>
                            <div class="current-stage-info">
                                <label class="stage-label">Current Stage:</label>
                                <div class="stage-value-container">
                                    <span class="stage-value">
                                        @if($workflowCurrentStageId)
                                            @php
                                                $workflowCurrentStage = $workflowAllStages->where('id', $workflowCurrentStageId)->first();
                                            @endphp
                                            {{ $workflowCurrentStage ? $workflowCurrentStage->name : 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="overall-progress-container">
                                <label class="progress-label">Overall Progress:</label>
                                <div class="progress-circle-wrapper">
                                    @php
                                        $workflowTotalStages = $workflowAllStages->count();
                                        $workflowCurrentStageRow = $workflowCurrentStageId ? $workflowAllStages->firstWhere('id', $workflowCurrentStageId) : null;
                                        $workflowCurrentSortVal = $workflowCurrentStageRow ? ($workflowCurrentStageRow->sort_order ?? $workflowCurrentStageRow->id) : null;
                                        $workflowCurrentStageIndex = $workflowCurrentSortVal !== null ? $workflowAllStages->where(fn($s) => ($s->sort_order ?? $s->id) <= $workflowCurrentSortVal)->count() : 0;
                                        $workflowProgressPercentage = $workflowTotalStages > 0 ? round(($workflowCurrentStageIndex / $workflowTotalStages) * 100) : 0;
                                    @endphp
                                    <div class="progress-circle" data-progress="{{ $workflowProgressPercentage }}">
                                        <svg class="progress-ring" width="80" height="80">
                                            <circle class="progress-ring-circle-bg" cx="40" cy="40" r="36" fill="transparent" stroke="#e9ecef" stroke-width="6"/>
                                            <circle class="progress-ring-circle" cx="40" cy="40" r="36" fill="transparent" stroke="#007bff" stroke-width="6" stroke-dasharray="{{ 2 * M_PI * 36 }}" stroke-dashoffset="{{ 2 * M_PI * 36 * (1 - $workflowProgressPercentage / 100) }}"/>
                                        </svg>
                                        <div class="progress-text">{{ $workflowProgressPercentage }}%</div>
                                    </div>
                                </div>
                            </div>
                            <div class="deadline-section mt-3">
                                <div class="form-group mb-0">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="workflow-set-deadline" data-matter-id="{{ $workflowSelectedMatter->id }}"
                                            {{ $workflowSelectedMatter->deadline ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="workflow-set-deadline">Set Deadline</label>
                                    </div>
                                    <div class="workflow-deadline-date-wrapper mt-2" style="{{ $workflowSelectedMatter->deadline ? '' : 'display: none;' }}">
                                        <label for="workflow-deadline-date" class="sr-only">Deadline Date</label>
                                        <input type="date" class="form-control form-control-sm" id="workflow-deadline-date"
                                            value="{{ $workflowSelectedMatter->deadline ? \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('Y-m-d') : '' }}"
                                            data-matter-id="{{ $workflowSelectedMatter->id }}"
                                            style="max-width: 180px;">
                                        <small class="form-text text-muted">Select the matter deadline date.</small>
                                    </div>
                                    @if($workflowSelectedMatter->deadline)
                                        <div class="mt-2">
                                            <span class="badge badge-info"><i class="fas fa-calendar-alt"></i> Deadline: {{ \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('d/m/Y') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="stage-navigation-buttons">
                                @php
                                    $workflowIsDiscontinued = ($workflowSelectedMatter->matter_status ?? 1) == 0;
                                    $workflowCanReopen = ((Auth::guard('admin')->user()->role ?? 0) == 1);
                                @endphp
                                @if($workflowIsDiscontinued)
                                    {{-- Discontinued matter: show Reopen (Admin only), Change Workflow --}}
                                    @if($workflowCanReopen)
                                    <button class="btn btn-primary btn-sm matter-detail-reopen-btn" id="workflow-tab-reopen" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Reopen Matter">
                                        <i class="fas fa-redo"></i> Reopen
                                    </button>
                                    @endif
                                    <button class="btn btn-outline-secondary btn-sm" id="workflow-tab-change-workflow" data-matter-id="{{ $workflowSelectedMatter->id }}" data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}" title="Change workflow for this matter">
                                        <i class="fas fa-exchange-alt"></i> Change Workflow
                                    </button>
                                @else
                                    {{-- Active matter: show normal workflow buttons --}}
                                    @php
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
                                        $workflowLastStage = $workflowAllStages->last();
                                        $workflowIsLastStage = $workflowNextStage === null;
                                    @endphp
                                    <button class="btn btn-outline-primary btn-sm" id="workflow-tab-back-to-previous-stage" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Back to Previous Stage" {{ $workflowIsFirstStage ? 'disabled' : '' }}>
                                        <i class="fas fa-angle-left"></i> Back to Previous Stage
                                    </button>
                                    @php
                                        $workflowNextBtnDisabled = $workflowIsLastStage;
                                        $workflowNextBtnTitle = 'Proceed to Next Stage';
                                        if ($workflowIsVerificationStage && !$workflowCanVerifyAndProceed) {
                                            $workflowNextBtnDisabled = true;
                                            $workflowNextBtnTitle = 'Only a Migration Agent (or Admin) can verify and proceed.';
                                        }
                                    @endphp
                                    <button class="btn btn-success btn-sm" id="workflow-tab-proceed-to-next-stage" data-matter-id="{{ $workflowSelectedMatter->id }}" data-next-stage-name="{{ $workflowNextStageName ?? '' }}" data-current-stage-name="{{ $workflowCurrentStageName ?? '' }}" data-is-verification-stage="{{ $workflowIsVerificationStage ? '1' : '0' }}" data-can-verify-and-proceed="{{ $workflowCanVerifyAndProceed ? '1' : '0' }}" title="{{ $workflowNextBtnTitle }}" {{ $workflowNextBtnDisabled ? 'disabled' : '' }}>
                                        Proceed to Next Stage <i class="fas fa-angle-right"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" id="workflow-tab-discontinue" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Discontinue Matter">
                                        <i class="fas fa-ban"></i> Discontinue
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" id="workflow-tab-change-workflow" data-matter-id="{{ $workflowSelectedMatter->id }}" data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}" title="Change workflow for this matter">
                                        <i class="fas fa-exchange-alt"></i> Change Workflow
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="info-card">
                        <h5>
                            <i class="fas fa-folder-open"></i> {{ $workflowMatterName }} ({{ $workflowMatterNumber }})
                        </h5>

                        @if($workflowAllStages->count() > 0)
                            <div class="workflow-stages-container mt-3">
                                <div class="workflow-stages-list">
                                    @foreach($workflowAllStages as $stage)
                                        @php
                                            $wfIsActive = ($workflowCurrentStageId && $workflowCurrentStageId == $stage->id);
                                            $stageSort = $stage->sort_order ?? $stage->id;
                                            $currentStageRow = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
                                            $currentStageSort = $currentStageRow ? ($currentStageRow->sort_order ?? $currentStageRow->id) : null;
                                            $wfIsCompleted = ($workflowCurrentStageId && $currentStageSort !== null && $stageSort < $currentStageSort);
                                            $wfStageClass = $wfIsActive ? 'workflow-stage-active' : ($wfIsCompleted ? 'workflow-stage-completed' : 'workflow-stage-pending');
                                        @endphp
                                        <div class="workflow-stage-item {{ $wfStageClass }}">
                                            <span class="stage-name">{{ $stage->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <p class="text-muted">No workflow stages defined. Add stages from Admin Console → Workflows.</p>
                        @endif
                    </div>
                </div>
            </div>
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

                // If at Verification stage (Payment, Service Agreement, Forms), Migration Agent must tick and add optional note
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
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'; }

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

        // Verification: Payment, Service Agreement, Forms modal - Submit handled by delegated handler in client_portal.blade.php

        // Decision Received modal: Submit - handled by delegated handler in client_portal.blade.php

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
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

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
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
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
                if (!matterId) { alert('Error: Matter ID not found'); return; }
                document.getElementById('discontinue-matter-id').value = matterId;
                document.getElementById('discontinue-reason').value = '';
                document.getElementById('discontinue-notes').value = '';
                document.querySelector('.discontinue-reason-error strong').textContent = '';
                $('#discontinue-matter-modal').modal('show');
            });
        }

        // Discontinue Matter modal: Submit
        var discontinueSubmitBtn = document.getElementById('discontinue-matter-submit');
        if (discontinueSubmitBtn) {
            discontinueSubmitBtn.addEventListener('click', function() {
                var reasonSelect = document.getElementById('discontinue-reason');
                var reason = reasonSelect.value;
                var matterId = document.getElementById('discontinue-matter-id').value;
                var notes = document.getElementById('discontinue-notes').value;
                var errEl = document.querySelector('.discontinue-reason-error strong');

                if (!reason || reason.trim() === '') {
                    errEl.textContent = 'Please select a reason for discontinuing.';
                    return;
                }
                errEl.textContent = '';

                var btn = this;
                var orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

                var currentTab = document.querySelector('.client-nav-button.active')?.getAttribute('data-tab') || 'personaldetails';

                fetch('{{ route("clients.matter.discontinue") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '', 'Accept': 'application/json' },
                    body: JSON.stringify({ matter_id: matterId, discontinue_reason: reason, discontinue_notes: notes, current_tab: currentTab })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    if (data.status) {
                        $('#discontinue-matter-modal').modal('hide');
                        alert(data.message || 'Matter has been discontinued.');
                        // Redirect to the base client URL (no matter ref) so the page
                        // auto-selects the next active matter instead of keeping the
                        // discontinued matter's ref in the URL which breaks all filtering.
                        var clientEncodeId = window.ClientDetailConfig ? window.ClientDetailConfig.encodeId : null;
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else if (clientEncodeId) {
                            window.location.href = '/clients/detail/' + clientEncodeId;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert(data.message || 'Failed to discontinue matter.');
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

        // Reopen button (for discontinued matters - workflow tab and client portal tab)
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.matter-detail-reopen-btn');
            if (!btn) return;
            e.preventDefault();
            var matterId = btn.getAttribute('data-matter-id');
            if (!matterId) return;
            if (!confirm('Reopen this matter? It will be moved back to active matters.')) return;
            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reopening...';
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
    });
})();
</script>
@endpush
