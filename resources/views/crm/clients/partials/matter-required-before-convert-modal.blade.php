{{-- Shown after redirect when user tries to convert a lead without an active assigned matter --}}
@if (session('matter_required_before_convert'))
<div class="modal fade" id="matterRequiredBeforeConvertModal" tabindex="-1" role="dialog" aria-labelledby="matterRequiredBeforeConvertLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="matterRequiredBeforeConvertLabel">Assign a matter first</h4>
            </div>
            <div class="modal-body">
                <p>You must assign an active matter before converting this lead to a client. Add or select a matter in the Matter section on this page, save if needed, then return to the lead detail page and try again.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('matterRequiredBeforeConvertModal')) return;
    if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
        jQuery('#matterRequiredBeforeConvertModal').modal('show');
    }
});
</script>
@endpush
@endif
