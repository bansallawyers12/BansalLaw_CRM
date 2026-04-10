{{-- Task Detail Side Panel --}}
<div class="task-detail-panel" id="taskDetailPanel">
    <div class="task-detail-overlay" onclick="closeTaskDetail()"></div>
    
    <div class="task-detail-content">
        <div class="task-detail-header">
            <button class="task-detail-close" onclick="closeTaskDetail()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="task-detail-body">
            <div class="task-detail-section">
                <div class="task-detail-complete">
                    <input type="checkbox" id="taskDetailComplete" class="task-detail-checkbox">
                    <label for="taskDetailComplete" class="task-detail-title" id="taskDetailTitle">
                        Task Title
                    </label>
                </div>
            </div>
            
            <div class="task-detail-section">
                <div class="task-detail-row">
                    <i class="fas fa-user detail-icon"></i>
                    <div class="task-detail-info">
                        <div class="task-detail-label">Client</div>
                        <div class="task-detail-value">
                            <a href="#" id="taskDetailClientLink" class="task-client-link">
                                <span id="taskDetailClientName">Client Name</span>
                                <span id="taskDetailClientCode" class="task-detail-code"></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="task-detail-section">
                <div class="task-detail-row">
                    <i class="far fa-calendar detail-icon"></i>
                    <div class="task-detail-info">
                        <div class="task-detail-label">Due Date</div>
                        <div class="task-detail-value" id="taskDetailDueDate">
                            Date
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="task-detail-section">
                <div class="task-detail-row">
                    <i class="fas fa-user-tie detail-icon"></i>
                    <div class="task-detail-info">
                        <div class="task-detail-label">Assigned To</div>
                        <div class="task-detail-value" id="taskDetailAssigned">
                            Assignee
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="task-detail-section">
                <div class="task-detail-row">
                    <i class="fas fa-align-left detail-icon"></i>
                    <div class="task-detail-info">
                        <div class="task-detail-label">Description</div>
                        <div class="task-detail-value task-detail-description" id="taskDetailDescription">
                            Description text
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="task-detail-footer">
            <button type="button" class="task-detail-action-btn btn-complete-task" onclick="completeTaskFromDetail()">
                <i class="fas fa-check"></i>
                Mark as Complete
            </button>
            <button type="button" class="task-detail-action-btn btn-extend-task" onclick="extendTaskFromDetail()">
                <i class="fas fa-calendar-plus"></i>
                Extend Deadline
            </button>
        </div>
    </div>
</div>

<style>
.task-detail-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 10000;
    display: none;
    pointer-events: none;
}

.task-detail-panel.active {
    display: block;
    pointer-events: all;
}

.task-detail-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.task-detail-panel.active .task-detail-overlay {
    opacity: 1;
}

.task-detail-content {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 420px;
    max-width: 100%;
    background: #ffffff;
    border-left: 1px solid #c8dcef;
    box-shadow: -4px 0 24px rgba(30, 61, 96, 0.1);
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

.task-detail-panel.active .task-detail-content {
    transform: translateX(0);
}

.task-detail-header {
    padding: 16px 20px;
    border-bottom: 1px solid #c8dcef;
    display: flex;
    justify-content: flex-end;
    background: #ffffff;
}

.task-detail-close {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: #1e3d60;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.task-detail-close:hover,
.task-detail-close:focus {
    background: #c8dcef;
    color: #1e3d60;
}

.task-detail-body {
    flex: 1;
    overflow-y: auto;
    padding: 24px 20px;
    background: #f0f6ff;
}

.task-detail-section {
    margin-bottom: 24px;
}

.task-detail-complete {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.task-detail-checkbox {
    appearance: none;
    width: 24px;
    height: 24px;
    border: 2px solid #c8dcef;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    flex-shrink: 0;
    margin-top: 2px;
}

.task-detail-checkbox:hover {
    border-color: #3a6fa8;
}

.task-detail-checkbox:checked {
    background: #1e3d60;
    border-color: #1e3d60;
}

.task-detail-checkbox:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 16px;
    font-weight: bold;
}

.task-detail-title {
    font-size: 18px;
    font-weight: 600;
    color: #1a2c40;
    line-height: 1.4;
    cursor: pointer;
    flex: 1;
}

.task-detail-row {
    display: flex;
    gap: 12px;
}

.detail-icon {
    width: 20px;
    text-align: center;
    color: #5e7a90;
    margin-top: 2px;
    flex-shrink: 0;
}

.task-detail-info {
    flex: 1;
}

.task-detail-label {
    font-size: 12px;
    color: #5e7a90;
    font-weight: 600;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.task-detail-value {
    font-size: 14px;
    color: #1a2c40;
    line-height: 1.5;
}

.task-client-link {
    color: #1e3d60;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
}

.task-client-link:hover {
    color: #3a6fa8;
    text-decoration: underline;
}

.task-detail-code {
    color: #5e7a90;
    font-size: 13px;
    font-weight: 500;
}

.task-detail-description {
    white-space: pre-wrap;
    word-break: break-word;
}

.task-detail-footer {
    padding: 16px 20px;
    border-top: 1px solid #c8dcef;
    background: #f0f6ff;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Footer actions — high specificity so global button / .btn-success rules never win */
.task-detail-panel .task-detail-footer .task-detail-action-btn {
    width: 100%;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    background-image: none !important;
    -webkit-appearance: none;
    appearance: none;
}

/* docs/theme.md — primary button: --navy; hover: --sidebar-active */
.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-complete-task {
    background-color: var(--navy, #1e3d60) !important;
    border: 1px solid var(--navy, #1e3d60) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(30, 61, 96, 0.2);
}

.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-complete-task i {
    color: #ffffff !important;
}

.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-complete-task:hover,
.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-complete-task:focus {
    background-color: var(--sidebar-active, #3a6fa8) !important;
    border-color: var(--sidebar-active, #3a6fa8) !important;
    color: #ffffff !important;
}

.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-complete-task:focus {
    outline: none;
    box-shadow: 0 2px 6px rgba(30, 61, 96, 0.2), 0 0 0 2px rgba(58, 111, 168, 0.25);
}

.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-extend-task {
    background-color: var(--card-bg, #ffffff) !important;
    border: 1px solid var(--border, #c8dcef) !important;
    color: var(--navy, #1e3d60) !important;
    box-shadow: none;
}

.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-extend-task i {
    color: var(--navy, #1e3d60) !important;
}

.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-extend-task:hover,
.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-extend-task:focus {
    background-color: var(--sidebar-hover, #c8dcef) !important;
    border-color: var(--border, #c8dcef) !important;
    color: var(--navy, #1e3d60) !important;
}

.task-detail-panel .task-detail-footer .task-detail-action-btn.btn-extend-task:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(58, 111, 168, 0.2);
}

@media (max-width: 768px) {
    .task-detail-content {
        width: 100%;
    }
}
</style>

