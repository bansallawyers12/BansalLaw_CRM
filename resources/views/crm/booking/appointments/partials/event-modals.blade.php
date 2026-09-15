{{-- Shared Appointment Details + cancellation confirm modals (dashboard + booking calendar). --}}
<link rel="stylesheet" href="{{ asset('css/booking-appointment-modal.css') }}?v={{ @filemtime(public_path('css/booking-appointment-modal.css')) ?: time() }}">

<div class="modal fade booking-calendar-modal appointment-detail-modal" id="eventModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" id="eventModalDialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="appointment-detail-modal__heading">
                    <span class="appointment-detail-modal__icon" id="eventModalIcon" aria-hidden="true">
                        <i class="fa-solid fa-calendar-check"></i>
                    </span>
                    <div>
                        <h5 class="modal-title mb-0" id="eventModalTitle">Appointment Details</h5>
                        <p class="appointment-detail-modal__subtitle mb-0 d-none" id="eventModalSubtitle"></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="eventModalBody">
                {{-- Content loaded dynamically --}}
            </div>
            <div class="modal-footer appointment-detail-modal__footer">
                <button type="button" id="courtHearingEditBtn" class="btn btn-outline-primary d-none">
                    <i class="fa-solid fa-pen-to-square"></i> Edit Appointment
                </button>
                <div class="appointment-detail-modal__footer-actions ms-auto">
                    <button type="button" id="courtHearingCancelEditBtn" class="btn btn-secondary d-none">Cancel</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="courtHearingSaveBtn" class="btn btn-primary d-none">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                    <a href="#" id="viewFullDetails" class="btn btn-primary d-none" target="_blank">
                        <i class="fa-solid fa-user"></i> Open Client
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade booking-calendar-modal" id="cancellationConfirmModal" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Cancellation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to change the status to <strong>cancelled</strong>?</p>
                <div class="mb-3">
                    <label class="form-label" for="cancelReasonInput">Cancellation reason <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cancelReasonInput" placeholder="Enter cancellation reason" required>
                    <div class="text-danger small d-none" id="cancelReasonError">Cancellation reason is required.</div>
                </div>
                <div class="form-check mb-0">
                    <input type="checkbox" class="form-check-input" id="sendCancellationEmailCheck" checked>
                    <label class="form-check-label" for="sendCancellationEmailCheck">Send cancellation confirmation to client</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">
                    <i class="fa-solid fa-xmark"></i> Confirm Cancellation
                </button>
            </div>
        </div>
    </div>
</div>
