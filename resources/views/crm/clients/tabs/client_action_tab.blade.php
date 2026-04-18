{{-- Demo-only: client detail new design — Action tab (workflow/checklist removed from nav). --}}
<div class="tab-pane" id="clientaction-tab">
    <div class="card full-width">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <h3 class="mb-0"><i class="fas fa-bolt"></i> Action</h3>
        </div>
        <p class="text-muted mb-3">
            Log follow-ups and matter-related actions. Use <strong>Update stage</strong> in the header to change workflow stages (same as production workflow tab).
        </p>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="button" class="btn btn-primary create_note_d" datatype="note">
                <i class="fas fa-plus"></i> Add note / action
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cdn-update-stage-modal">
                <i class="fas fa-stream"></i> Update stage
            </button>
        </div>
    </div>
</div>
