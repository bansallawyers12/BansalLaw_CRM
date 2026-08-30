@php
    use App\Services\ClientNotesListService;
    use App\Support\NoteAttachmentHtml;
    use App\Support\NoteDescriptionHtml;

    $canEditDatetime = ClientNotesListService::canEditNoteDatetime($actor ?? null);
@endphp
@foreach ($notes as $list)
    @php
        $author = $list->user;
        $authorFirstName = $author->first_name ?? 'NA';
        $authorLastName = $author->last_name ?? 'NA';
        $typeMeta = ClientNotesListService::typeMeta($list->task_group ?? null);
        $typeLabel = $typeMeta['label'];
        $typeInlineClass = $typeMeta['inline'];
        $notePhone = trim((string) ($list->mobile_number ?? ''));
    @endphp
    <div class="note-card-redesign{{ (int) ($list->pin ?? 0) === 1 ? ' pinned' : '' }}"
         data-matterid="{{ $list->matter_id }}"
         id="note_id_{{ $list->id }}"
         data-id="{{ $list->id }}"
         data-type="{{ $typeLabel }}"
         data-note-date="{{ $list->updated_at }}">
        @if ((int) ($list->pin ?? 0) === 1)
            <div class="pined_note">
                <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
            </div>
        @endif

        <div class="date-time-menu-container">
            <span class="author-updated-date-time">{{ date('d/m/Y h:i A', strtotime((string) $list->updated_at)) }}</span>
            <div class="note-toggle-btn-div">
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle note-toggle-btn-div-type" type="button" data-bs-toggle="dropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item opennoteform" data-id="{{ $list->id }}" href="javascript:;">Edit</a>
                        @if ($canEditDatetime)
                            <a class="dropdown-item editdatetime" data-id="{{ $list->id }}" href="javascript:;">Edit Date Time</a>
                        @endif
                        <a data-id="{{ $list->id }}" data-href="deletenote" class="dropdown-item deletenote" href="javascript:;">Delete</a>
                        @if ((int) ($list->pin ?? 0) === 1)
                            <a data-id="{{ $list->id }}" class="dropdown-item pinnote" href="javascript:;">Unpin</a>
                        @else
                            <a data-id="{{ $list->id }}" class="dropdown-item pinnote" href="javascript:;">Pin</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="note-card-info">
            <span class="author-name-created">{{ $authorFirstName }} {{ $authorLastName }} added the</span><span class="note-type-inline {{ $typeInlineClass }}">{{ $typeLabel }} notes</span>
        </div>

        @if ($notePhone !== '')
            <div class="note-meta-redesign" style="margin-bottom: 10px;">
                <i class="fa-solid fa-phone" style="color: #2563eb;" aria-hidden="true"></i>
                <strong style="margin-left: 6px;">Number:</strong> {{ $notePhone }}
            </div>
        @endif

        @if ($list->spend_mins !== null && $list->spend_mins !== '')
            <div class="note-spend-mins-badge">
                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                {{ (int) $list->spend_mins }} mins
            </div>
        @endif

        <div class="note-content-redesign">
            @if (! empty($list->description))
                {!! NoteDescriptionHtml::forDisplay($list->description) !!}
            @endif
        </div>
        {!! NoteAttachmentHtml::forNoteCard($list->attachments) !!}
    </div>
@endforeach
