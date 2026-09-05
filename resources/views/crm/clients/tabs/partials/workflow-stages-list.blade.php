@if($workflowAllStages->count() > 0)
    @php
        $showStepNumbers = !empty($showStepNumbers);
        $showLocks = !empty($showLocks);
        $listAria = $showStepNumbers ? 'Workflow stages' : null;
    @endphp
    <div class="workflow-stages-container {{ $containerClass ?? '' }}">
        @if(!empty($stagesHeading))
            <div class="workflow-stages-heading">{{ $stagesHeading }}</div>
        @endif
        <div class="workflow-stages-list {{ $listClass ?? '' }}" @if($listAria) role="list" aria-label="{{ $listAria }}" @endif>
            @foreach($workflowAllStages as $stage)
                @php
                    $wfIsActive = ($workflowCurrentStageId && $workflowCurrentStageId == $stage->id);
                    $stageSort = $stage->sort_order ?? $stage->id;
                    $currentStageRow = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
                    $currentStageSort = $currentStageRow ? ($currentStageRow->sort_order ?? $currentStageRow->id) : null;
                    $wfIsCompleted = ($workflowCurrentStageId && $currentStageSort !== null && $stageSort < $currentStageSort);
                    $wfIsPending = ! $wfIsActive && ! $wfIsCompleted;
                    $wfStageClass = $wfIsActive ? 'workflow-stage-active' : ($wfIsCompleted ? 'workflow-stage-completed' : 'workflow-stage-pending');
                @endphp
                <div class="workflow-stage-item {{ $wfStageClass }}" @if($showStepNumbers) role="listitem" @endif>
                    @if($showStepNumbers)
                        <span class="stage-step-num" aria-hidden="true">
                            @if($wfIsCompleted)
                                <i class="fa-solid fa-check"></i>
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                    @endif
                    <span class="stage-name">{{ $stage->name }}</span>
                    @if($showLocks && $wfIsPending)
                        <span class="stage-lock" aria-hidden="true" title="Locked until earlier stages are completed">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@else
    <p class="text-muted mb-0">No workflow stages defined. Add stages from Admin Console → Workflows.</p>
@endif
