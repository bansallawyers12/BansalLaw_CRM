@php
    $hasStreamColumn = $hasStreamColumn ?? \Illuminate\Support\Facades\Schema::hasColumn('matters', 'stream');
    $firstTemplate = \App\Models\EmailTemplate::forMatter($list->id)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->first();
@endphp
<tr id="id_{{ $list->id }}" class="mat-data-row" data-mat-id="{{ $list->id }}">
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
    <td class="text-nowrap text-end mat-actions-cell">
        <div class="mat-action-btns">
            <button type="button" class="btn btn-sm mat-btn-action mat-btn-view mat-view-btn" data-mat-id="{{ $list->id }}" title="View matter details">
                <i class="fa-regular fa-eye"></i> <span>View</span>
            </button>
            <button type="button" class="btn btn-sm mat-btn-action mat-btn-edit mat-edit-btn" data-mat-id="{{ $list->id }}" title="Edit matter">
                <i class="fa-solid fa-pen-to-square"></i> <span>Edit</span>
            </button>
            <div class="dropdown d-inline-block mat-dropdown-wrap">
                <button class="btn btn-sm mat-btn-action mat-btn-more dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'
                    aria-expanded="false" title="More options">
                    <span>More</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end mat-dropdown-menu">
                    <li class="mat-dropdown-header">Matter Actions</li>
                    @if($firstTemplate)
                    <li>
                        <a class="dropdown-item mat-dropdown-item" href="{{ route('adminconsole.features.matteremailtemplate.edit', [$firstTemplate->id, $list->id]) }}">
                            <span class="mat-dropdown-icon mat-icon-email"><i class="fa-regular fa-pen-to-square"></i></span>
                            <span class="mat-dropdown-label">Edit first email</span>
                        </a>
                    </li>
                    @else
                    <li>
                        <a class="dropdown-item mat-dropdown-item" href="{{ route('adminconsole.features.matteremailtemplate.create', ['matter_id' => $list->id]) }}">
                            <span class="mat-dropdown-icon mat-icon-email"><i class="fa-regular fa-envelope-open"></i></span>
                            <span class="mat-dropdown-label">Create first email</span>
                        </a>
                    </li>
                    @endif
                    <li>
                        <a class="dropdown-item mat-dropdown-item" href="{{ route('upload_checklists.matter', $list->id) }}">
                            <span class="mat-dropdown-icon mat-icon-checklist"><i class="fa-solid fa-list-check"></i></span>
                            <span class="mat-dropdown-label">Matter checklist</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item mat-dropdown-item" href="{{ route('adminconsole.features.matterotheremailtemplate.index', $list->id) }}">
                            <span class="mat-dropdown-icon mat-icon-templates"><i class="fa-regular fa-envelope"></i></span>
                            <span class="mat-dropdown-label">Email templates</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider mat-dropdown-divider"></li>
                    <li>
                        <button type="button" class="dropdown-item mat-dropdown-item mat-dropdown-item--danger mat-delete-btn" data-mat-id="{{ $list->id }}">
                            <span class="mat-dropdown-icon mat-icon-delete"><i class="fa-regular fa-trash-can"></i></span>
                            <span class="mat-dropdown-label">Delete</span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </td>
</tr>
