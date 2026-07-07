@php
    $tab = $tab ?? 'active';
    $colspan = 6;
@endphp
<div class="table-responsive common_table staff-list-table-wrap">
    <table class="table staff-list-table mb-0">
        <thead>
            <tr>
                <th>Name</th>
                <th>Position</th>
                <th>Office</th>
                <th>Role</th>
                <th>Status</th>
                <th class="text-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody id="staff-list-tbody" class="{{ ($totalData ?? 0) > 0 ? 'tdata' : '' }}">
            @if(($totalData ?? 0) > 0)
                @foreach ($lists as $list)
                    @include('AdminConsole.staff.partials.row', ['list' => $list, 'tab' => $tab])
                @endforeach
            @else
                <tr id="staff-empty-row">
                    <td class="text-center text-muted py-4" colspan="{{ $colspan }}">
                        <i class="fas fa-users fa-2x mb-2 d-block opacity-50"></i>
                        No staff found for this tab.
                        @if(!empty($searchBy))
                            Try a different search term.
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
