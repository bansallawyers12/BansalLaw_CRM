{{-- Shown after redirect when user tries to convert a lead without an active assigned matter --}}
@if (session('matter_required_before_convert'))
<div class="modal fade" id="matterRequiredBeforeConvertModal" tabindex="-1" role="dialog" aria-labelledby="matterRequiredBeforeConvertLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="matterRequiredBeforeConvertLabel">Assign a matter first</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You must assign an active matter before converting this lead to a client. Add or select a matter in the Matter section on this page, save if needed, then return to the lead detail page and try again.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('matterRequiredBeforeConvertModal');
    if (!modalEl) return;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery(modalEl).modal('show');
    }
});
</script>
@endpush
@endif
