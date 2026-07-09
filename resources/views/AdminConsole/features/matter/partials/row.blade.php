@php
    $hasStreamColumn = $hasStreamColumn ?? \Illuminate\Support\Facades\Schema::hasColumn('matters', 'stream');
    $firstTemplate = \App\Models\EmailTemplate::forMatter($list->id)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->first();
@endphp
<tr id="id_{{ $list->id }}" data-mat-id="{{ $list->id }}">
    <td>
        <button type="button" class="btn btn-link p-0 text-start mat-view-btn" data-mat-id="{{ $list->id }}">
            <strong>{{ $list->title ?: config('constants.empty') }}</strong>
        </button>
        @if(!empty($list->nick_name))
            <div class="text-muted small">{{ $list->nick_name }}</div>
        @endif
    </td>
    @if($hasStreamColumn)
    <td class="text-muted small">
        {{ $list->stream ? \Illuminate\Support\Arr::get(config('matter_streams.streams', []), $list->stream, $list->stream) : '—' }}
    </td>
    @endif
    <td class="text-nowrap text-end">
        <button type="button" class="btn btn-sm btn-outline-primary mat-view-btn" data-mat-id="{{ $list->id }}">
            <i class="fa-regular fa-eye"></i> View
        </button>
        <button type="button" class="btn btn-sm btn-primary mat-edit-btn" data-mat-id="{{ $list->id }}">
            <i class="fa-solid fa-pen-to-square"></i> Edit
        </button>
        <div class="dropdown d-inline-block">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'
                aria-expanded="false">More</button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if($firstTemplate)
                <li><a class="dropdown-item" href="{{ route('adminconsole.features.matteremailtemplate.edit', [$firstTemplate->id, $list->id]) }}"><i class="fa-regular fa-pen-to-square"></i> Edit first email</a></li>
                @else
                <li><a class="dropdown-item" href="{{ route('adminconsole.features.matteremailtemplate.create', ['matter_id' => $list->id]) }}"><i class="fa-regular fa-pen-to-square"></i> Create first email</a></li>
                @endif
                <li><a class="dropdown-item" href="{{ route('upload_checklists.matter', $list->id) }}"><i class="fa-solid fa-list"></i> Matter checklist</a></li>
                <li><a class="dropdown-item" href="{{ route('adminconsole.features.matterotheremailtemplate.index', $list->id) }}"><i class="fa-solid fa-envelope"></i> Email templates</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><button type="button" class="dropdown-item text-danger mat-delete-btn" data-mat-id="{{ $list->id }}"><i class="fa-solid fa-trash"></i> Delete</button></li>
            </ul>
        </div>
    </td>
</tr>
