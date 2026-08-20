{{-- Shared note file picker (images, PDF, Office, etc.) --}}
<div class="form-group enhanced-form-group note-attachments-field">
    <label class="form-label">
        Attachments
        <span class="text-muted" style="font-weight: 500; font-size: 0.85rem;">(optional)</span>
    </label>
    <label class="note-dropzone">
        <input type="file"
               name="attachments[]"
               class="note-attachments-input"
               multiple
               accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.rtf,.odt,.zip,image/*">
        <div class="note-dropzone-inner">
            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
            <p>Drop files here or <span>browse</span></p>
            <small>Images, PDF, Word, Excel and similar files. Max 10 files, 20 MB each.</small>
        </div>
    </label>
    <div class="note-existing-attachments"></div>
    <ul class="note-selected-files"></ul>
</div>
