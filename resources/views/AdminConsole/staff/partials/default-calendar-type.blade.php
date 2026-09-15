@php
    use App\Services\StaffPersonalCalendarFeedService;

    $calendarTypes = StaffPersonalCalendarFeedService::CALENDAR_TYPES;
    $current = old(
        'default_calendar_type',
        $isEdit ? ($fetchedData->default_calendar_type ?? '') : ''
    );
    $hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('staff', 'default_calendar_type');
@endphp

@if($hasColumn)
<div class="form-group">
    <label for="{{ ($fieldPrefix ?? 'staff') }}_default_calendar_type">Default website calendar</label>
    <select
        name="default_calendar_type"
        id="{{ ($fieldPrefix ?? 'staff') }}_default_calendar_type"
        class="form-control"
    >
        <option value="automatic" @selected($current === '' || $current === null || $current === 'automatic')>
            Automatic (name / email hints, else Ajay)
        </option>
        @foreach($calendarTypes as $key => $label)
            <option value="{{ $key }}" @selected((string) $current === (string) $key)>
                {{ $label }}
            </option>
        @endforeach
    </select>
    <small class="text-muted d-block mt-1">
        Controls which website booking calendar opens first on the dashboard and booking calendar for this staff member.
        Leave on Automatic to use name/email hints (Ajay → Ajay, Michael/Kunal → Michael), otherwise Ajay.
    </small>
</div>
@endif
