{{-- Shared note file picker (images, PDF, Office, etc.) --}}
<div class="form-group enhanced-form-group note-attachments-field mb-0">
    <label class="form-label">
        Attachments
        <span class="text-muted note-attachments-optional">(optional)</span>
    </label>
    <label class="note-dropzone note-dropzone--compact">
        <input type="file"
               name="attachments[]"
               class="note-attachments-input"
               multiple
               accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.rtf,.odt,.zip,image/*">
        <div class="note-dropzone-inner">
            <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
            <p><span>Browse files</span> or drag anywhere on this window</p>
            <small>Max 10 files · 20 MB each</small>
        </div>
    </label>
    <div class="note-existing-attachments"></div>
    <ul class="note-selected-files"></ul>
</div>
