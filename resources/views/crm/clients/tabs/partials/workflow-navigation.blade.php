<div class="stage-navigation-buttons {{ $navigationClass ?? '' }}">
    @if($workflowIsReadOnly)
        <span class="text-muted small"><i class="fa-solid fa-lock"></i> View only — this matter is closed.</span>
    @elseif($workflowIsDiscontinued)
        @if($workflowCanReopen)
            <button class="btn btn-primary btn-sm matter-detail-reopen-btn" id="workflow-tab-reopen" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Reopen Matter">
                <i class="fa-solid fa-arrow-rotate-right"></i> Reopen
            </button>
        @else
            @if($workflowSelectedMatter->reopen_requested_by ?? null)
                <button class="btn btn-secondary btn-sm" disabled title="Reopen Requested">
                    <i class="fa-solid fa-clock"></i> Reopen Requested
                </button>
            @else
                <button class="btn btn-warning btn-sm matter-detail-request-reopen-btn" id="workflow-tab-request-reopen" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Request Admin to Reopen Matter">
                    <i class="fa-solid fa-hand-paper"></i> Request Reopen
                </button>
            @endif
        @endif
        <button class="btn btn-outline-secondary btn-sm" id="workflow-tab-change-workflow" data-matter-id="{{ $workflowSelectedMatter->id }}" data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}" title="Change workflow for this matter">
            <i class="fa-solid fa-right-left"></i> Change Workflow
        </button>
    @elseif(!empty($workflowInModal))
        <button type="button" class="btn btn-outline-primary btn-sm" id="workflow-tab-back-to-previous-stage" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Back to Previous Stage" {{ $workflowIsFirstStage ? 'disabled' : '' }}>
            <i class="fa-solid fa-angle-left"></i> Back
        </button>
        <div class="dropdown workflow-nav-more">
            <button type="button" class="btn btn-sm dropdown-toggle workflow-nav-more__btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-haspopup="true" aria-expanded="false">
                More
            </button>
            <ul class="dropdown-menu dropdown-menu-end workflow-nav-more__menu">
                @if($workflowCanDiscontinue)
                    <li>
                        <button type="button" class="dropdown-item text-danger" id="workflow-tab-discontinue" data-matter-id="{{ $workflowSelectedMatter->id }}">
                            <i class="fa-solid fa-ban"></i> Discontinue
                        </button>
                    </li>
                @endif
                <li>
                    <button type="button" class="dropdown-item" id="workflow-tab-change-workflow" data-matter-id="{{ $workflowSelectedMatter->id }}" data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}">
                        <i class="fa-solid fa-right-left"></i> Change Workflow
                    </button>
                </li>
            </ul>
        </div>
        <button type="button" class="btn btn-success btn-sm" id="workflow-tab-proceed-to-next-stage" data-matter-id="{{ $workflowSelectedMatter->id }}" data-next-stage-name="{{ $workflowNextStageName ?? '' }}" data-current-stage-name="{{ $workflowCurrentStageName ?? '' }}" title="{{ $workflowNextBtnTitle }}" {{ $workflowNextBtnDisabled ? 'disabled' : '' }}>
            {{ $workflowNextBtnLabel }} <i class="fa-solid fa-angle-right"></i>
        </button>
    @else
        <div class="d-inline-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-outline-primary btn-sm" id="workflow-tab-back-to-previous-stage" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Back to Previous Stage" {{ $workflowIsFirstStage ? 'disabled' : '' }}>
                <i class="fa-solid fa-angle-left"></i> Back to Previous Stage
            </button>
            <button type="button" class="btn btn-success btn-sm" id="workflow-tab-proceed-to-next-stage" data-matter-id="{{ $workflowSelectedMatter->id }}" data-next-stage-name="{{ $workflowNextStageName ?? '' }}" data-current-stage-name="{{ $workflowCurrentStageName ?? '' }}" title="{{ $workflowNextBtnTitle }}" {{ $workflowNextBtnDisabled ? 'disabled' : '' }}>
                {{ $workflowNextBtnLabel }} <i class="fa-solid fa-angle-right"></i>
            </button>
        </div>
        @if($workflowCanDiscontinue)
            <button class="btn btn-outline-danger btn-sm" id="workflow-tab-discontinue" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Discontinue Matter">
                <i class="fa-solid fa-ban"></i> Discontinue
            </button>
        @endif
        <button class="btn btn-outline-secondary btn-sm" id="workflow-tab-change-workflow" data-matter-id="{{ $workflowSelectedMatter->id }}" data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}" title="Change workflow for this matter">
            <i class="fa-solid fa-right-left"></i> Change Workflow
        </button>
    @endif
</div>
