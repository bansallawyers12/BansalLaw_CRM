{{-- Client detail Tasks tab — matter-scoped via client_matter_id. Internal slug remains clientaction for routing/JS. --}}
<div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'clientaction' ? ' active' : '' }}" id="clientaction-tab" role="tabpanel" aria-labelledby="cdn-tab-clientaction">
    <div class="card full-width cdn-tasks-tab-card">
        <div class="card-body cdn-tasks-tab-card__body">
            <h2 class="cdn-tasks-tab-title">
                <span class="cdn-tasks-tab-title__icon" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                <span>Tasks</span>
                <span id="cdn-matter-task-stats" class="cdn-tasks-tab-stats" aria-live="polite"></span>
            </h2>

            <div id="cdn-matter-tasks" class="cdn-matter-tasks">
                <div class="cdn-matter-task-composer">
                    <label class="visually-hidden" for="cdn-matter-task-title">Add a task</label>
                    <input type="text" class="form-control cdn-matter-task-composer__input" id="cdn-matter-task-title" maxlength="500" placeholder="Add a task…" autocomplete="off">
                    <div class="cdn-matter-task-composer__due-wrap">
                        <i class="fa-regular fa-calendar cdn-matter-task-composer__due-icon" aria-hidden="true"></i>
                        <label class="visually-hidden" for="cdn-matter-task-due">Due date</label>
                        <input type="text" class="form-control cdn-matter-task-composer__due" id="cdn-matter-task-due" placeholder="Due date" autocomplete="off" inputmode="numeric" aria-label="Due date">
                    </div>
                    <button type="button" class="btn btn-primary cdn-matter-task-composer__btn" id="cdn-matter-task-add">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add
                    </button>
                </div>

                <div class="cdn-matter-task__list" aria-live="polite" aria-relevant="additions text"></div>
            </div>
        </div>
    </div>
</div>
