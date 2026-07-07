@php
    $hasStreamColumn = $hasStreamColumn ?? \Illuminate\Support\Facades\Schema::hasColumn('matters', 'stream');
    $colspan = $hasStreamColumn ? 3 : 2;
@endphp
<div class="table-responsive common_table mat-list-table-wrap">
    <table class="table mat-list-table mb-0">
        <thead>
            <tr>
                <th>Matter name</th>
                @if($hasStreamColumn)
                <th>Stream</th>
                @endif
                <th class="text-end text-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody id="mat-list-tbody" class="{{ ($totalData ?? 0) > 0 ? 'tdata' : '' }}">
            @if(($totalData ?? 0) > 0)
                @foreach ($lists as $list)
                    @include('AdminConsole.features.matter.partials.row', [
                        'list' => $list,
                        'hasStreamColumn' => $hasStreamColumn,
                    ])
                @endforeach
            @else
                <tr id="mat-empty-row">
                    <td class="text-center text-muted py-4" colspan="{{ $colspan }}">
                        <i class="fas fa-briefcase fa-2x mb-2 d-block opacity-50"></i>
                        No matters found.
                        @if(!empty($searchBy))
                            Try a different search term.
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
