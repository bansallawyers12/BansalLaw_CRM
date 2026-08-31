@props([
    'casesData' => [],
])

@if(count($casesData) > 0)
    <ul class="case-list" id="cases-attention-list">
        @foreach($casesData as $case)
            <x-dashboard.case-item :case="$case" />
        @endforeach
    </ul>
    <div class="todo-infinite-loader" id="casesInfiniteLoader" hidden>
        <i class="fa-solid fa-spinner fa-spin"></i> Loading more…
    </div>
@else
    <div class="empty-state-modern empty-state-modern--compact">
        <i class="fa-solid fa-folder-open fa-2x"></i>
        <h4>No Recent Activity</h4>
        <p>No open matters have recent activity or a deadline in the next 7 days.</p>
    </div>
@endif
