@php
    $selectAllId = $selectAllId ?? 'add_task_select_all';
    $hiddenSelectId = $hiddenSelectId ?? 'add_task_rem_cat';
    $errorId = $errorId ?? 'add_task_assignees_error';
    $staffMembers = $staffMembers ?? collect();
@endphp
<div class="form-group add-task-assignees-group">
    <label class="control-label">
        <i class="fa-solid fa-users"></i> Assignees
        <span class="selected-count assignee-count-badge" aria-live="polite"></span>
    </label>
    <div class="assignee-picker-panel dropdown-multi-select">
        <button type="button" class="assignee-picker-trigger" aria-haspopup="listbox" aria-expanded="false">
            <span class="assignee-picker-chips-wrap">
                <span class="assignee-picker-placeholder">Select assignees</span>
                <span class="assignee-picker-chips" aria-live="polite"></span>
            </span>
            <i class="fa-solid fa-chevron-down assignee-picker-chevron" aria-hidden="true"></i>
        </button>
        <div class="assignee-picker-dropdown">
            <div class="assignee-picker-toolbar">
                <div class="assignee-picker-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" class="form-control assignee-search-input" placeholder="Search assignees..." autocomplete="off" aria-label="Search assignees">
                </div>
            </div>
            <label class="assignee-select-all">
                <input type="checkbox" id="{{ $selectAllId }}" class="assignee-select-all-input">
                <span>Select all</span>
            </label>
            <div class="assignee-list" role="listbox" aria-multiselectable="true">
                @foreach ($staffMembers as $member)
                    @php
                        if ($member instanceof \App\Models\Staff) {
                            $staffId = (int) $member->id;
                            $firstName = (string) $member->first_name;
                            $lastName = (string) $member->last_name;
                            $branch = \App\Models\Branch::where('id', $member->office_id)->first();
                            $officeName = (string) ($branch->office_name ?? '');
                        } else {
                            $staffId = (int) ($member['id'] ?? 0);
                            $firstName = (string) ($member['first_name'] ?? '');
                            $lastName = (string) ($member['last_name'] ?? '');
                            $officeName = (string) ($member['office_name'] ?? '');
                        }
                        $searchText = strtolower($firstName . $lastName . $officeName);
                        $searchText = str_replace(' ', '', $searchText);
                        $displayName = trim($firstName . ' ' . $lastName);
                    @endphp
                    <label class="assignee-item assignee-picker-row dropdown-item"
                        data-searchtext="{{ e($searchText) }}"
                        data-staff-id="{{ $staffId }}"
                        data-staff-name="{{ e($displayName) }}">
                        <input type="checkbox" class="checkbox-item" value="{{ $staffId }}">
                        <span class="assignee-picker-row__text">{{ e($displayName) }}@if($officeName !== '') ({{ e($officeName) }})@endif</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>
    <select class="d-none" id="{{ $hiddenSelectId }}" name="rem_cat[]" multiple="multiple">
        @foreach ($staffMembers as $member)
            @php
                if ($member instanceof \App\Models\Staff) {
                    $staffId = (int) $member->id;
                    $label = trim($member->first_name . ' ' . $member->last_name);
                } else {
                    $staffId = (int) ($member['id'] ?? 0);
                    $label = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
                }
            @endphp
            <option value="{{ $staffId }}">{{ e($label) }}</option>
        @endforeach
    </select>
    <div id="{{ $errorId }}" class="error-message"></div>
</div>
