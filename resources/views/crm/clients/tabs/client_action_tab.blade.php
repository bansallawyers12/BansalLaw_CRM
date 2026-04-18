{{-- Demo-only: client detail new design — Tasks tab (stage changes via header Update Stage only). Internal slug remains clientaction for routing/JS. --}}
<div class="tab-pane" id="clientaction-tab">
    <div class="card full-width">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <h3 class="mb-0"><i class="fas fa-tasks"></i> Tasks</h3>
        </div>

        <div id="cdn-matter-tasks" class="cdn-matter-tasks mb-4">
            <h4 class="h6 fw-semibold mb-2"><i class="fas fa-tasks text-secondary"></i> Matter task list</h4>
            <p class="text-muted small mb-3">
                Tasks for the <strong>currently selected matter</strong> only (e.g. obtain instructions, file in court). They stay on this tab and are not copied to the Notes list.
            </p>
            <div class="cdn-matter-task__list mb-3"></div>
            <div class="row g-2 align-items-stretch">
                <div class="col mb-2 mb-sm-0">
                    <label class="visually-hidden" for="cdn-matter-task-title">New task</label>
                    <input type="text" class="form-control" id="cdn-matter-task-title" maxlength="500" placeholder="Add a task for this matter…" autocomplete="off">
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-primary" id="cdn-matter-task-add">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <h4 class="h6 fw-semibold mb-2"><i class="fas fa-sticky-note text-secondary"></i> Notes</h4>
        <p class="text-muted small mb-3">
            Rich notes and narrative follow-ups (saved to the Notes tab and activity history).
        </p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary create_note_d" datatype="note">
                <i class="fas fa-plus"></i> Add note
            </button>
        </div>
    </div>
</div>
