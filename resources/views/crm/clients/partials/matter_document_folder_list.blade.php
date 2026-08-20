@foreach($fetchd as $fetch)
    @php
        $admin = $fetch->staff;
        $visaDocumentType = \App\Models\VisaDocumentType::where('id', $fetch->folder_name)->first();
        $previewUrl = url('/documents/preview/' . $fetch->id);
        $downloadFilename = $fetch->myfile_key ?: trim(($fetch->file_name ?? '') . '.' . ($fetch->filetype ?? ''), '.');
    @endphp
    <tr class="drow" data-matterid="{{ $fetch->client_matter_id }}" data-catid="{{ $fetch->folder_name }}" id="id_{{ $fetch->id }}">
        <td style="white-space: initial;">
            <div data-id="{{ $fetch->id }}" data-visachecklistname="{{ \App\Support\DocumentLabel::forDisplay($fetch->checklist) }}" class="visachecklist-row md-checklist-row" title="Uploaded by: {{ htmlspecialchars($admin->first_name ?? 'NA') }} on {{ date('d/m/Y H:i', strtotime($fetch->created_at)) }}">
                <span class="md-checklist-label">{{ \App\Support\DocumentLabel::forDisplay($fetch->checklist) }}</span>
                <div class="checklist-actions">
                    @if (!$fetch->file_name)
                        <a href="javascript:;" class="edit-checklist-btn" data-id="{{ $fetch->id }}" data-checklist="{{ \App\Support\DocumentLabel::forDisplay($fetch->checklist) }}" title="Edit Checklist Name">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <a href="javascript:;" class="delete-checklist-btn" data-id="{{ $fetch->id }}" data-checklist="{{ \App\Support\DocumentLabel::forDisplay($fetch->checklist) }}" title="Delete Checklist">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    @endif
                </div>
            </div>
        </td>
        <td style="white-space: initial;">
            @if ($fetch->file_name)
                @php
                    $matterFileExt = strtolower((string) ($fetch->filetype ?? ''));
                    if (in_array($matterFileExt, ['xls', 'xlsx', 'csv', 'ods'], true)) {
                        $matterFileIcon = 'fa-file-excel';
                    } elseif (in_array($matterFileExt, ['doc', 'docx', 'rtf', 'odt'], true)) {
                        $matterFileIcon = 'fa-file-word';
                    } elseif ($matterFileExt === 'pdf') {
                        $matterFileIcon = 'fa-file-pdf';
                    } elseif (in_array($matterFileExt, ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'], true)) {
                        $matterFileIcon = 'fa-file-video';
                    } else {
                        $matterFileIcon = 'fa-file-image';
                    }
                @endphp
                <div data-id="{{ $fetch->id }}" data-name="{{ htmlspecialchars($fetch->file_name) }}" class="doc-row" title="Uploaded by: {{ htmlspecialchars($admin->first_name ?? 'NA') }} on {{ date('d/m/Y H:i', strtotime($fetch->created_at)) }}" oncontextmenu="showVisaFileContextMenu(event, {{ (int) $fetch->id }}, {{ json_encode($fetch->filetype) }}, {{ json_encode($previewUrl) }}, {{ json_encode((string) $fetch->folder_name) }}, {{ json_encode($fetch->status ?? 'draft') }}); return false;">
                    <a href="javascript:void(0);" onclick="previewFile({{ json_encode($fetch->filetype) }}, {{ json_encode($previewUrl) }}, {{ json_encode('preview-container-matter-' . $fetch->folder_name) }})">
                        <i class="fa-solid {{ $matterFileIcon }} matter-doc-file-icon"></i> <span>{{ htmlspecialchars($fetch->file_name . '.' . $fetch->filetype) }}</span>
                    </a>
                </div>
            @else
                <div class="migration_upload_document" style="display: inline-block;">
                    <form method="POST" enctype="multipart/form-data" id="mig_upload_form_{{ $fetch->id }}">
                        @csrf
                        <input type="hidden" name="clientid" value="{{ $fetch->client_id }}">
                        <input type="hidden" name="client_matter_id" value="{{ $fetch->client_matter_id }}">
                        <input type="hidden" name="fileid" value="{{ $fetch->id }}">
                        <input type="hidden" name="type" value="client">
                        <input type="hidden" name="doctype" value="matter">
                        <input type="hidden" name="doccategory" value="{{ $visaDocumentType->title ?? '' }}">
                        <div class="document-drag-drop-zone visa-doc-drag-zone"
                             data-fileid="{{ $fetch->id }}"
                             data-doccategory="{{ $fetch->folder_name }}"
                             data-formid="mig_upload_form_{{ $fetch->id }}">
                            <div class="drag-zone-inner">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                            </div>
                        </div>
                        <input class="migdocupload d-none"
                               data-fileid="{{ $fetch->id }}"
                               data-doccategory="{{ $fetch->folder_name }}"
                               type="file"
                               name="document_upload"
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.mp4,.webm,.mov,.m4v,.avi,.mkv,video/mp4,video/webm,video/quicktime,video/*"
                               style="display: none;">
                    </form>
                </div>
            @endif
        </td>
        <td>
            @if ($fetch->myfile)
                <a class="renamechecklist" data-id="{{ $fetch->id }}" href="javascript:;" style="display: none;"></a>
                <a class="renamedoc" data-id="{{ $fetch->id }}" href="javascript:;" style="display: none;"></a>
                <a class="download-file" data-document-id="{{ $fetch->id }}" data-id="{{ $fetch->id }}" data-filename="{{ htmlspecialchars($downloadFilename) }}" href="#" style="display: none;"></a>
                <a class="notuseddoc" data-id="{{ $fetch->id }}" data-doctype="matter" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
            @endif
        </td>
    </tr>
@endforeach
