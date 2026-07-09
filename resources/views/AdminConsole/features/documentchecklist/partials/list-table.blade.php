@php $colspan = 3; @endphp
<div class="table-responsive common_table dcl-list-table-wrap">
    <table class="table dcl-list-table mb-0">
        <thead>
            <tr>
                <th>Name</th>
                <th>Document type</th>
                <th class="text-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody id="dcl-list-tbody" class="{{ ($totalData ?? 0) > 0 ? 'tdata' : '' }}">
            @if(($totalData ?? 0) > 0)
                @foreach ($lists as $list)
                    @include('AdminConsole.features.documentchecklist.partials.row', ['list' => $list])
                @endforeach
            @else
                <tr id="dcl-empty-row">
                    <td class="text-center text-muted py-4" colspan="{{ $colspan }}">
                        <i class="fa-solid fa-list-check fa-2x mb-2 d-block opacity-50"></i>
                        No checklists found.
                        @if(!empty($searchBy))
                            Try a different search term.
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
