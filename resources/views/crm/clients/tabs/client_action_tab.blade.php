{{-- Demo-only: client detail new design — Tasks tab (stage changes via header Update Stage only). Internal slug remains clientaction for routing/JS. --}}
<div class="tab-pane" id="clientaction-tab" role="tabpanel" aria-labelledby="cdn-tab-clientaction">
    <div class="card full-width cdn-tasks-tab-card">
        <div class="card-body cdn-tasks-tab-card__body">
            <h2 class="cdn-tasks-tab-title">
                <span class="cdn-tasks-tab-title__icon" aria-hidden="true"><i class="fas fa-tasks"></i></span>
                <span>Tasks</span>
            </h2>

            <div id="cdn-matter-tasks" class="cdn-matter-tasks">
                <div class="cdn-matter-task__list" aria-live="polite" aria-relevant="additions text"></div>

                <div class="cdn-matter-task-composer">
                    <label class="visually-hidden" for="cdn-matter-task-title">Add a task</label>
                    <input type="text" class="form-control cdn-matter-task-composer__input" id="cdn-matter-task-title" maxlength="500" placeholder="Add a task…" autocomplete="off">
                    <button type="button" class="btn btn-primary cdn-matter-task-composer__btn" id="cdn-matter-task-add">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
