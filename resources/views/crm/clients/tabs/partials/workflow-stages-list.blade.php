@if($workflowAllStages->count() > 0)
    <div class="workflow-stages-container {{ $containerClass ?? '' }}">
        <div class="workflow-stages-list {{ $listClass ?? '' }}" @if(!empty($showStepNumbers)) role="list" aria-label="Workflow stages" @endif>
            @foreach($workflowAllStages as $stage)
                @php
                    $wfIsActive = ($workflowCurrentStageId && $workflowCurrentStageId == $stage->id);
                    $stageSort = $stage->sort_order ?? $stage->id;
                    $currentStageRow = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
                    $currentStageSort = $currentStageRow ? ($currentStageRow->sort_order ?? $currentStageRow->id) : null;
                    $wfIsCompleted = ($workflowCurrentStageId && $currentStageSort !== null && $stageSort < $currentStageSort);
                    $wfStageClass = $wfIsActive ? 'workflow-stage-active' : ($wfIsCompleted ? 'workflow-stage-completed' : 'workflow-stage-pending');
                @endphp
                <div class="workflow-stage-item {{ $wfStageClass }}" @if(!empty($showStepNumbers)) role="listitem" @endif>
                    @if(!empty($showStepNumbers))
                        <span class="stage-step-num" aria-hidden="true">
                            @if($wfIsCompleted)
                                <i class="fa-solid fa-check"></i>
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                    @endif
                    <span class="stage-name">{{ $stage->name }}</span>
                </div>
            @endforeach
        </div>
    </div>
@else
    <p class="text-muted mb-0">No workflow stages defined. Add stages from Admin Console → Workflows.</p>
@endif
