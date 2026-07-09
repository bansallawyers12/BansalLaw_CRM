@php $colspan = 4; @endphp
<div class="table-responsive common_table mdt-list-table-wrap">
    <table class="table mdt-list-table mb-0">
        <thead>
            <tr>
                <th>Title</th>
                <th>Client name</th>
                <th>Client matter name</th>
                <th class="text-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody id="mdt-list-tbody" class="{{ ($totalData ?? 0) > 0 ? 'tdata' : '' }}">
            @if(($totalData ?? 0) > 0)
                @foreach ($lists as $list)
                    @include('AdminConsole.features.matterdocumenttype.partials.row', ['list' => $list])
                @endforeach
            @else
                <tr id="mdt-empty-row">
                    <td class="text-center text-muted py-4" colspan="{{ $colspan }}">
                        <i class="fa-solid fa-folder-open fa-2x mb-2 d-block opacity-50"></i>
                        No matter document folders found.
                        @if(!empty($searchBy))
                            Try a different search term.
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
