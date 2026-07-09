@foreach($fetchd as $fetch)
    @php
        $admin = $fetch->staff;
        $previewUrl = url('/documents/preview/' . $fetch->id);
        $downloadFilename = $fetch->myfile_key ?: basename(parse_url((string) $fetch->myfile, PHP_URL_PATH) ?: (string) $fetch->myfile);
        $fileIcon = in_array(strtolower($fetch->filetype ?? ''), ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'], true) ? 'fa-file-video' : 'fa-file-image';
    @endphp
    <tr class="drow" id="id_{{ $fetch->id }}">
        <td style="white-space: initial;">
            <div data-id="{{ $fetch->id }}" data-personalchecklistname="{{ htmlspecialchars($fetch->checklist) }}" class="personalchecklist-row" title="Uploaded by: {{ htmlspecialchars($admin->first_name ?? 'NA') }} on {{ date('d/m/Y H:i', strtotime($fetch->created_at)) }}" style="display: flex; align-items: center; gap: 8px;">
                <span style="flex: 1;">{{ htmlspecialchars($fetch->checklist) }}</span>
                <div class="checklist-actions" style="display: flex; gap: 5px;">
                    @if (!$fetch->file_name)
                        <a href="javascript:;" class="edit-checklist-btn" data-id="{{ $fetch->id }}" data-checklist="{{ htmlspecialchars($fetch->checklist) }}" title="Edit Checklist Name" style="color: #007bff; cursor: pointer;">
                            <i class="fa-solid fa-edit"></i>
                        </a>
                        <a href="javascript:;" class="delete-checklist-btn" data-id="{{ $fetch->id }}" data-checklist="{{ htmlspecialchars($fetch->checklist) }}" title="Delete Checklist" style="color: #dc3545; cursor: pointer;">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    @endif
                </div>
            </div>
        </td>
        <td style="white-space: initial;">
            @if ($fetch->file_name)
                <div data-id="{{ $fetch->id }}" data-name="{{ htmlspecialchars($fetch->file_name) }}" class="doc-row" title="Uploaded by: {{ htmlspecialchars($admin->first_name ?? 'NA') }} on {{ date('d/m/Y H:i', strtotime($fetch->created_at)) }}" oncontextmenu="showFileContextMenu(event, {{ (int) $fetch->id }}, {{ json_encode($fetch->filetype) }}, {{ json_encode($previewUrl) }}, {{ json_encode((string) $folderName) }}, {{ json_encode($fetch->status ?? 'draft') }}); return false;">
                    <a href="javascript:void(0);" onclick="previewFile({{ json_encode($fetch->filetype) }}, {{ json_encode($previewUrl) }}, {{ json_encode('preview-container-' . $folderName) }})">
                        <i class="fas {{ $fileIcon }}"></i> <span>{{ htmlspecialchars($fetch->file_name . '.' . $fetch->filetype) }}</span>
                    </a>
                </div>
            @else
                <div class="upload_document" style="display:inline-block;">
                    <form method="POST" enctype="multipart/form-data" id="upload_form_{{ $fetch->id }}">
                        @csrf
                        <input type="hidden" name="clientid" value="{{ $clientid }}">
                        <input type="hidden" name="fileid" value="{{ $fetch->id }}">
                        <input type="hidden" name="type" value="client">
                        <input type="hidden" name="doctype" value="personal">
                        <input type="hidden" name="doccategory" value="{{ $doccategoryTitle }}">
                        <div class="document-drag-drop-zone personal-doc-drag-zone"
                             data-fileid="{{ $fetch->id }}"
                             data-doccategory="{{ $folderName }}"
                             data-formid="upload_form_{{ $fetch->id }}">
                            <div class="drag-zone-inner">
                                <i class="fa-solid fa-cloud-upload-alt"></i>
                                <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                            </div>
                        </div>
                        <input class="docupload d-none" data-fileid="{{ $fetch->id }}" data-doccategory="{{ $folderName }}" type="file" name="document_upload" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.mp4,.webm,.mov,.m4v,.avi,.mkv" style="display: none;">
                    </form>
                </div>
            @endif
        </td>
        <td>
            @if ($fetch->myfile)
                <a class="renamechecklist" data-id="{{ $fetch->id }}" href="javascript:;" style="display: none;"></a>
                <a class="renamedoc" data-id="{{ $fetch->id }}" href="javascript:;" style="display: none;"></a>
                <a class="download-file" data-document-id="{{ $fetch->id }}" data-id="{{ $fetch->id }}" data-filename="{{ htmlspecialchars($downloadFilename) }}" href="#" style="display: none;"></a>
                <a class="notuseddoc" data-id="{{ $fetch->id }}" data-doctype="personal" data-doccategory="{{ $doccategoryTitle }}" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
            @endif
        </td>
    </tr>
@endforeach
