{{-- Demo-only: client detail new design — Action tab (workflow/checklist removed from nav). --}}
<div class="tab-pane" id="clientaction-tab">
    <div class="card full-width">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <h3 class="mb-0"><i class="fas fa-bolt"></i> Action</h3>
        </div>
        <p class="text-muted mb-3">
            Log follow-ups, tasks, and matter-related actions. Full workflow stage controls remain on the production client record.
        </p>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="button" class="btn btn-primary create_note_d" datatype="note">
                <i class="fas fa-plus"></i> Add note / action
            </button>
            <a href="{{ route('clients.detail', array_filter(['client_id' => $encodeId ?? null, 'client_unique_matter_ref_no' => $id1 ?? null, 'tab' => 'workflow'], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-outline-secondary">
                <i class="fas fa-stream"></i> Workflow (production)
            </a>
        </div>
    </div>
</div>
