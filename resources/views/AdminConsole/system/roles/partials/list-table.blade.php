@php $colspan = 4; @endphp
<div class="table-responsive common_table roles-list-table-wrap">
    <table class="table roles-list-table mb-0">
        <thead>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>Permissions</th>
                <th class="text-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody id="roles-list-tbody" class="{{ ($totalData ?? 0) > 0 ? 'tdata' : '' }}">
            @if(($totalData ?? 0) > 0)
                @foreach ($lists as $list)
                    @include('AdminConsole.system.roles.partials.row', ['list' => $list])
                @endforeach
            @else
                <tr id="roles-empty-row">
                    <td class="text-center text-muted py-4" colspan="{{ $colspan }}">
                        <i class="fa-solid fa-user-shield fa-2x mb-2 d-block opacity-50"></i>
                        No roles found.
                        @if(!empty($searchBy))
                            Try a different search term.
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
