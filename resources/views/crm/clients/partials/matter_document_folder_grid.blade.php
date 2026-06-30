@foreach($fetchd as $fetch)
    @if ($fetch->myfile)
    @php
        $gridPreviewUrl = url('/documents/preview/' . $fetch->id);
        $gridDownloadFilename = $fetch->myfile_key ?: trim(($fetch->file_name ?? '') . '.' . ($fetch->filetype ?? ''), '.');
    @endphp
    <div class="grid_list">
        <div class="grid_col">
            <div class="grid_icon">
                <i class="fas fa-file-image"></i>
            </div>
            <div class="grid_content">
                <span id="grid_{{ $fetch->id }}" class="gridfilename">{{ $fetch->file_name }}</span>
                <div class="dropdown d-inline dropdown_ellipsis_icon">
                    <a class="dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                    <div class="dropdown-menu">
                        <a href="javascript:void(0);" class="dropdown-item" onclick="previewFile({{ json_encode($fetch->filetype) }}, {{ json_encode($gridPreviewUrl) }}, {{ json_encode('preview-container-migdocumnetlist') }})">Preview</a>
                        <a href="#" class="dropdown-item download-file" data-document-id="{{ $fetch->id }}" data-id="{{ $fetch->id }}" data-filename="{{ htmlspecialchars($gridDownloadFilename) }}">Download</a>
                        <a data-id="{{ $fetch->id }}" class="dropdown-item notuseddoc" data-doctype="matter" data-href="notuseddoc" href="javascript:;">Not Used</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
@endforeach
