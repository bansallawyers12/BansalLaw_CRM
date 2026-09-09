{{-- Client detail Tasks tab — matter-scoped via client_matter_id. Internal slug remains clientaction for routing/JS. --}}
<div class="tab-pane{{ strtolower((string) ($activeTab ?? '')) === 'clientaction' ? ' active' : '' }}" id="clientaction-tab" role="tabpanel" aria-labelledby="cdn-tab-clientaction">
    <div class="card full-width cdn-tasks-tab-card">
        <div class="card-body cdn-tasks-tab-card__body">
            <h2 class="cdn-tasks-tab-title">
                <span class="cdn-tasks-tab-title__icon" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                <span>Tasks &amp; reminders</span>
                <span id="cdn-matter-task-stats" class="cdn-tasks-tab-stats" aria-live="polite"></span>
            </h2>

            <div id="cdn-matter-tasks" class="cdn-matter-tasks">
                <div class="cdn-matter-task-composer" data-composer-kind="task">
                    <div class="cdn-matter-task-composer__kinds-wrap">
                        <span class="cdn-matter-task-composer__kinds-label" id="cdn-matter-task-kinds-label">What are you adding?</span>
                        <div class="cdn-matter-task-composer__kinds" role="group" aria-labelledby="cdn-matter-task-kinds-label">
                            <button type="button" class="cdn-matter-task-composer__kind is-active" data-kind="task" aria-pressed="true" title="Matter task — stays on this client matter">
                                <span class="cdn-matter-task-composer__kind-icon" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                                <span class="cdn-matter-task-composer__kind-copy">
                                    <span class="cdn-matter-task-composer__kind-title">Task</span>
                                    <span class="cdn-matter-task-composer__kind-desc">Stays on this matter</span>
                                </span>
                            </button>
                            <button type="button" class="cdn-matter-task-composer__kind cdn-matter-task-composer__kind--reminder" data-kind="reminder" aria-pressed="false" id="cdn-matter-task-kind-reminder" title="Personal reminder — shows on your My Calendar">
                                <span class="cdn-matter-task-composer__kind-icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
                                <span class="cdn-matter-task-composer__kind-copy">
                                    <span class="cdn-matter-task-composer__kind-title">Reminder</span>
                                    <span class="cdn-matter-task-composer__kind-desc">Goes on My Calendar</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="cdn-matter-task-composer__fields">
                        <label class="visually-hidden" for="cdn-matter-task-title">Add a task</label>
                        <input type="text" class="form-control cdn-matter-task-composer__input" id="cdn-matter-task-title" maxlength="500" placeholder="Add a task…" autocomplete="off">
                        <div class="cdn-matter-task-composer__due-wrap">
                            <i class="fa-regular fa-calendar cdn-matter-task-composer__due-icon" aria-hidden="true"></i>
                            <label class="visually-hidden" for="cdn-matter-task-due" id="cdn-matter-task-due-label">Due date</label>
                            <input type="text" class="form-control cdn-matter-task-composer__due" id="cdn-matter-task-due" placeholder="Due date" autocomplete="off" inputmode="numeric" aria-label="Due date">
                        </div>
                        <button type="button" class="btn btn-primary cdn-matter-task-composer__btn" id="cdn-matter-task-add">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i> <span id="cdn-matter-task-add-label">Add task</span>
                        </button>
                    </div>
                </div>
                <p class="cdn-matter-task-composer__hint" id="cdn-matter-task-composer-hint" aria-live="polite">
                    <strong>Task</strong> — stays on this matter. Tick the checkbox when done.
                </p>

                <div class="cdn-matter-task__list" aria-live="polite" aria-relevant="additions text"></div>
            </div>
        </div>
    </div>
</div>
