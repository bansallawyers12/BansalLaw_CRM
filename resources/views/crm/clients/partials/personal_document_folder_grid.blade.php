@foreach($fetchd as $fetch)
    @if ($fetch->myfile)
        @php
            $gridPreviewUrl = url('/documents/preview/' . $fetch->id);
            $gridDownloadFilename = $fetch->myfile_key ?: basename(parse_url((string) $fetch->myfile, PHP_URL_PATH) ?: (string) $fetch->myfile);
            $gridFileIcon = in_array(strtolower($fetch->filetype ?? ''), ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'], true) ? 'fa-file-video' : 'fa-file-image';
        @endphp
        <div class="grid_list" id="gid_{{ $fetch->id }}">
            <div class="grid_col">
                <div class="grid_icon">
                    <i class="fa-solid {{ $gridFileIcon }}"></i>
                </div>
                <div class="grid_content">
                    <span id="grid_{{ $fetch->id }}" class="gridfilename">{{ $fetch->file_name }}</span>
                    <div class="dropdown d-inline dropdown_ellipsis_icon">
                        <a class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical"></i></a>
                        <div class="dropdown-menu">
                            <a href="javascript:void(0);" class="dropdown-item" onclick="previewFile({{ json_encode($fetch->filetype) }}, {{ json_encode($gridPreviewUrl) }}, {{ json_encode('preview-container-' . $folderName) }})">Preview</a>
                            <a href="#" class="dropdown-item download-file" data-document-id="{{ $fetch->id }}" data-id="{{ $fetch->id }}" data-filename="{{ htmlspecialchars($gridDownloadFilename) }}">Download</a>
                            <a data-id="{{ $fetch->id }}" class="dropdown-item notuseddoc" data-doctype="personal" data-doccategory="{{ $doccategoryTitle }}" data-href="notuseddoc" href="javascript:;">Not Used</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach
