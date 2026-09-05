<!-- Appointment Modal -->
<div class="modal fade add_appointment custom_modal sa-appoint-modal" id="create_appoint" tabindex="-1" role="dialog" aria-labelledby="create_interestModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header appointment-schedule-modal-header sa-appoint-modal__header">
				<h5 class="modal-title mb-0" id="interestModalLabel">Schedule Appointment</h5>
				<x-crm.modal-close />
			</div>
			<div class="modal-body sa-appoint-modal__body">
				@php
					$__crmAppointmentConsultants = \App\Models\AppointmentConsultant::query()
						->where('is_active', true)
						->orderBy('name')
						->get()
						->unique('id');
				@endphp
				<form method="post" action="{{URL::to('/add-appointment-book')}}" name="appointform" id="appointform" autocomplete="off" enctype="multipart/form-data">
				    @csrf
				    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="client_unique_id" value="{{$fetchedData->client_id}}">
					<input type="hidden" name="timezone" value="Australia/Melbourne">

					<div class="row g-2 mb-2">
						<div class="col-md-6">
							<label for="client_name" class="sa-label">Client Reference <span class="span_req">*</span></label>
							<input type="text" name="client_name" id="client_name" value="{{ @$fetchedData->client_id }}" class="form-control" data-valid="required" autocomplete="off" placeholder="Client reference" readonly>
						</div>
						<div class="col-md-6 nature_of_enquiry_row" id="nature_of_enquiry">
							<label for="noe_id" class="sa-label">Nature of Enquiry <span class="span_req">*</span></label>
							<select class="form-control enquiry_item modern-select" name="noe_id" id="noe_id" data-valid="required">
								<option value="">Select Nature of Enquiry</option>
								@foreach (config('booking_nature_of_enquiry.crm') as $noe)
									<option value="{{ $noe['id'] }}">{{ $noe['label'] }}</option>
								@endforeach
							</select>
						</div>
					</div>

					<div class="services_row mb-2" id="services" style="display: none;">
						<label class="sa-label mb-2">Services <span class="span_req">*</span></label>
						<div class="sa-service-grid">
							<label class="sa-service-card service-card-compact service-promo-free" data-service-id="promo_free">
								<input type="radio" class="services_item sa-service-card__input" name="radioGroup" value="promo_free" id="service_promo_free">
								<span class="sa-service-card__radio" aria-hidden="true"></span>
								<span class="sa-service-card__meta">
									<span class="sa-service-card__title">Free consultation</span>
									<span class="sa-service-card__time">10 minutes</span>
								</span>
								<span class="sa-service-card__price">Free</span>
							</label>
							<label class="sa-service-card service-card-compact" data-service-id="paid">
								<input type="radio" class="services_item sa-service-card__input" name="radioGroup" value="paid" id="service_paid">
								<span class="sa-service-card__radio" aria-hidden="true"></span>
								<span class="sa-service-card__meta">
									<span class="sa-service-card__title">Standard</span>
									<span class="sa-service-card__time">30 minutes</span>
								</span>
								<span class="sa-service-card__price">$150</span>
							</label>
							<label class="sa-service-card service-card-compact" data-service-id="paid_extended">
								<input type="radio" class="services_item sa-service-card__input" name="radioGroup" value="paid_extended" id="service_paid_extended">
								<span class="sa-service-card__radio" aria-hidden="true"></span>
								<span class="sa-service-card__meta">
									<span class="sa-service-card__title">Extended</span>
									<span class="sa-service-card__time">1 hour</span>
								</span>
								<span class="sa-service-card__price">$220</span>
							</label>
						</div>
						<input type="hidden" id="service_id" name="service_id" value="">
					</div>

					<div class="appointment_row" id="appointment_details" style="display: none;">
						<div class="consultant-select-cls mb-2">
							<label for="add_appointment_consultant_id" class="sa-label">Consultant <span class="text-muted fw-normal">(optional)</span></label>
							<select class="form-control" name="consultant_id" id="add_appointment_consultant_id">
								<option value="">Select consultant…</option>
								@foreach ($__crmAppointmentConsultants as $consultant)
									<option value="{{ $consultant->id }}">{{ $consultant->name }} ({{ $consultant->calendar_type }})</option>
								@endforeach
							</select>
							<small class="sa-hint">Leave empty to assign automatically.</small>
						</div>
						{{-- Location fixed to Melbourne (2); hidden so booking/slot APIs and validation stay unchanged --}}
						<input type="hidden" name="inperson_address" class="inperson_address" value="2" data-val="2" id="crm_appointment_inperson_address" autocomplete="off">

						<div class="row g-2 mb-2 appointment_details_cls">
							<div class="col-md-6">
								<label for="appointment_details_select" class="sa-label">Appointment details <span class="span_req">*</span></label>
								<select class="form-control appointment_item" name="appointment_details" id="appointment_details_select" data-valid="required">
									<option value="">Select</option>
									<option value="phone">Phone Call</option>
									<option value="in_person">In person</option>
									<option value="video_call" id="video_call_option" style="display: none;">Video Call/Zoom</option>
								</select>
							</div>
							<div class="col-md-6">
								<label for="preferred_language" class="sa-label">Preferred Language <span class="span_req">*</span></label>
								<select class="form-control preferred_language" name="preferred_language" id="preferred_language" data-valid="required">
									<option value="">Select</option>
									<option value="Hindi">Hindi</option>
									<option value="English">English</option>
									<option value="Punjabi">Punjabi</option>
								</select>
							</div>
						</div>
					</div>

					<div class="info_row" id="info" style="display: none;">
						<div class="mb-2">
							<label for="description" class="sa-label">Details of Enquiry <span class="span_req">*</span></label>
							<textarea class="form-control description" id="description" rows="2" placeholder="Enter details of enquiry" name="description" data-valid="required"></textarea>
						</div>

						<div class="mb-2">
							<label class="sa-label mb-2">
								<i class="fa-solid fa-calendar-clock me-1"></i>
								Date &amp; Time <span class="span_req">*</span>
							</label>

							<div class="modern-datetime-container-wrapper">
								<div class="modern-datetime-container">
									<div class="datetime-content">
										<div class="calendar-section">
											<div class="section-header">
												<i class="fa-solid fa-calendar-check"></i>
												<span>Select Date</span>
											</div>
											<div class="calendar-wrapper">
												<div id="datetimepicker" class="datePickerCls"></div>
											</div>
										</div>

										<div class="timeslot-section">
											<div class="section-header">
												<i class="fa-solid fa-clock"></i>
												<span>Available Slots</span>
											</div>
											<div class="timeslot-wrapper">
												<div class="showselecteddate" style="display: none;"></div>
												<div class="timeslots" style="display: none;"></div>

												<div class="selected-date-display">
													<div class="date-icon">
														<i class="fa-solid fa-calendar-day"></i>
													</div>
													<div class="date-info">
														<div class="modern-selected-date">Select a date</div>
														<div class="modern-selected-day">from the calendar</div>
													</div>
												</div>

												<div class="timeslots-grid"></div>

												<div class="no-slots-message" style="display: none;">
													<div class="no-slots-icon">
														<i class="fa-solid fa-calendar-xmark"></i>
													</div>
													<div class="no-slots-text">
														<h6>No Available Slots</h6>
														<p>Please select another date</p>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="slot-overwrite-section sa-toggle-row mt-2">
								<label class="sa-switch" for="slot_overwrite">
									<input type="checkbox" class="form-check-input sa-switch__input" name="slot_overwrite" id="slot_overwrite" value="0">
									<span class="sa-switch__track" aria-hidden="true"><span class="sa-switch__knob"></span></span>
									<span class="sa-switch__copy">
										<span class="sa-switch__title">Slot overwrite</span>
										<span class="sa-switch__hint">Allow booking outside open slots</span>
									</span>
								</label>
								<input type="hidden" name="slot_overwrite_hidden" id="slot_overwrite_hidden" value="0">

								{{-- Hidden 0 so unchecked state is submitted; checked box overrides with 1 --}}
								<input type="hidden" name="send_confirmation_email" value="0">
								<label class="sa-switch" for="send_confirmation_email">
									<input type="checkbox" class="form-check-input sa-switch__input" name="send_confirmation_email" id="send_confirmation_email" value="1" checked>
									<span class="sa-switch__track" aria-hidden="true"><span class="sa-switch__knob"></span></span>
									<span class="sa-switch__copy">
										<span class="sa-switch__title">Send confirmation email</span>
										<span class="sa-switch__hint">Notify the client by email</span>
									</span>
								</label>
							</div>

							<div class="slotTimeOverwriteDivCls" style="display: none;">
								<?php
								if (!function_exists('generateTimeDropdown')) {
									function generateTimeDropdown($interval = 15) {
										$start = new DateTime('00:00');
										$end = new DateTime('23:45');
										$intervalDuration = new DateInterval('PT' . $interval . 'M');
										$times = new DatePeriod($start, $intervalDuration, $end);

										echo '<select class="slot_overwrite_time_dropdown form-control mt-2">';
										echo '<option value="">Select Time</option>';
										foreach ($times as $time) {
											$endTime = clone $time;
											$endTime->add($intervalDuration);
											echo '<option value="' . $time->format('g:i A') . ' - ' . $endTime->format('g:i A') . '">';
											echo $time->format('g:i A') . ' - ' . $endTime->format('g:i A');
											echo '</option>';
										}
										echo '</select>';
									}
								}
								generateTimeDropdown(10);
								?>
							</div>

							<input type="hidden" id="timeslot_col_date" name="appoint_date" value="">
							<input type="hidden" id="timeslot_col_time" name="appoint_time" value="">
							<span class="timeslot_col_date_time" role="alert" style="display: none;color:#f00;">Date and Time is required.</span>
						</div>
					</div>

					<div class="appointment-modal-actions-row sa-appoint-modal__footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
						<button onclick="customValidate('appointform')" type="button" class="btn btn-primary" id="appointform_save">
							Schedule Appointment
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<style>
/* Schedule Appointment modal — simple modern (scoped) */
#create_appoint.sa-appoint-modal .modal-content {
	font-family: 'Segoe UI', sans-serif;
	border: 1px solid var(--border, #c8dcef);
	border-radius: 12px;
	overflow: hidden;
	box-shadow: 0 12px 40px rgba(30, 61, 96, 0.16);
}

#create_appoint.sa-appoint-modal .sa-appoint-modal__header,
#create_appoint.sa-appoint-modal .appointment-schedule-modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	background: linear-gradient(135deg, var(--navy, #1e3d60) 0%, var(--sidebar-active, #3a6fa8) 100%) !important;
	background-image: linear-gradient(135deg, var(--navy, #1e3d60) 0%, var(--sidebar-active, #3a6fa8) 100%) !important;
	border-bottom: 3px solid var(--accent-gold, #c8992a) !important;
	padding: 16px 20px !important;
	color: #fff !important;
}

#create_appoint.sa-appoint-modal .modal-title,
#create_appoint.sa-appoint-modal .modal-header h5 {
	color: #fff !important;
	-webkit-text-fill-color: #fff !important;
	font-size: 1.125rem !important;
	font-weight: 700 !important;
	margin: 0 !important;
	letter-spacing: -0.01em !important;
	flex: 1;
	min-width: 0;
}

#create_appoint.sa-appoint-modal .sa-appoint-modal__header .crm-modal-close {
	display: inline-flex !important;
	align-items: center !important;
	justify-content: center !important;
	flex-shrink: 0 !important;
	width: 36px !important;
	height: 36px !important;
	min-width: 36px !important;
	margin: 0 0 0 auto !important;
	padding: 0 !important;
	border-radius: 8px !important;
	border: 1px solid rgba(255, 255, 255, 0.4) !important;
	background: rgba(255, 255, 255, 0.14) !important;
	color: #fff !important;
	opacity: 1 !important;
	font-size: 0 !important;
	line-height: 0 !important;
	overflow: hidden !important;
	cursor: pointer !important;
}

#create_appoint.sa-appoint-modal .sa-appoint-modal__header .crm-modal-close i,
#create_appoint.sa-appoint-modal .sa-appoint-modal__header .crm-modal-close span {
	display: none !important;
}

#create_appoint.sa-appoint-modal .sa-appoint-modal__header .crm-modal-close::before {
	content: "" !important;
	display: block !important;
	width: 14px !important;
	height: 14px !important;
	background-color: #fff !important;
	-webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='black' d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3E%3C/svg%3E") center / contain no-repeat !important;
	mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='black' d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3E%3C/svg%3E") center / contain no-repeat !important;
}

#create_appoint.sa-appoint-modal .sa-appoint-modal__header .crm-modal-close:hover,
#create_appoint.sa-appoint-modal .sa-appoint-modal__header .crm-modal-close:focus {
	background: rgba(255, 255, 255, 0.22) !important;
	border-color: rgba(255, 255, 255, 0.55) !important;
	color: #fff !important;
	opacity: 1 !important;
}

#create_appoint.sa-appoint-modal .sa-appoint-modal__body {
	padding: 14px 18px 8px !important;
	background: var(--card-bg, #fff) !important;
	max-height: min(78vh, 820px);
	overflow-y: auto;
	overflow-x: hidden;
}

#create_appoint.sa-appoint-modal .sa-label {
	display: block;
	font-size: 12px;
	font-weight: 600;
	color: var(--text-muted, #5e7a90);
	margin-bottom: 4px;
}

#create_appoint.sa-appoint-modal .span_req {
	color: var(--danger, #a83020);
	font-weight: 700;
}

#create_appoint.sa-appoint-modal .form-control,
#create_appoint.sa-appoint-modal .form-select {
	min-height: 38px;
	border-radius: 8px !important;
	border: 1px solid var(--border, #c8dcef) !important;
	color: var(--text-dark, #1a2c40) !important;
	background: var(--card-bg, #fff) !important;
	font-size: 0.9rem;
	box-shadow: none !important;
}

#create_appoint.sa-appoint-modal .form-control:focus,
#create_appoint.sa-appoint-modal .form-select:focus {
	border-color: var(--sidebar-active, #3a6fa8) !important;
	box-shadow: none !important;
	outline: none;
}

#create_appoint.sa-appoint-modal textarea.form-control {
	min-height: 64px;
	resize: vertical;
}

#create_appoint.sa-appoint-modal .sa-hint {
	display: block;
	margin-top: 4px;
	font-size: 11.5px;
	color: var(--text-muted, #5e7a90);
}

/* Service cards + modern radio */
#create_appoint.sa-appoint-modal .sa-service-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
}

#create_appoint.sa-appoint-modal .sa-service-card,
#create_appoint.sa-appoint-modal .service-card-compact {
	display: flex;
	align-items: center;
	gap: 10px;
	margin: 0;
	padding: 10px 12px;
	min-height: 56px;
	border: 1px solid var(--border, #c8dcef);
	border-radius: 10px;
	background: var(--card-bg, #fff);
	cursor: pointer;
	transition: border-color 0.15s ease, background-color 0.15s ease;
	box-shadow: none !important;
	transform: none !important;
}

#create_appoint.sa-appoint-modal .sa-service-card:hover {
	border-color: var(--sidebar-active, #3a6fa8);
}

#create_appoint.sa-appoint-modal .sa-service-card.selected,
#create_appoint.sa-appoint-modal .service-card-compact.selected,
#create_appoint.sa-appoint-modal .sa-service-card:has(.sa-service-card__input:checked) {
	border-color: var(--sidebar-active, #3a6fa8);
	background: rgba(58, 111, 168, 0.08);
}

#create_appoint.sa-appoint-modal .sa-service-card__input {
	position: absolute;
	opacity: 0;
	width: 1px;
	height: 1px;
	pointer-events: none;
}

#create_appoint.sa-appoint-modal .sa-service-card__radio {
	flex-shrink: 0;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	border: 2px solid #a8bdd0;
	background: #fff;
	position: relative;
	transition: border-color 0.15s ease, background-color 0.15s ease;
}

#create_appoint.sa-appoint-modal .sa-service-card__radio::after {
	content: '';
	position: absolute;
	inset: 3px;
	border-radius: 50%;
	background: var(--sidebar-active, #3a6fa8);
	transform: scale(0);
	transition: transform 0.15s ease;
}

#create_appoint.sa-appoint-modal .sa-service-card.selected .sa-service-card__radio,
#create_appoint.sa-appoint-modal .sa-service-card:has(.sa-service-card__input:checked) .sa-service-card__radio {
	border-color: var(--sidebar-active, #3a6fa8);
}

#create_appoint.sa-appoint-modal .sa-service-card.selected .sa-service-card__radio::after,
#create_appoint.sa-appoint-modal .sa-service-card:has(.sa-service-card__input:checked) .sa-service-card__radio::after {
	transform: scale(1);
}

#create_appoint.sa-appoint-modal .sa-service-card__meta {
	flex: 1 1 auto;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 1px;
}

#create_appoint.sa-appoint-modal .sa-service-card__title {
	font-size: 13px;
	font-weight: 700;
	color: var(--navy, #1e3d60);
	line-height: 1.2;
}

#create_appoint.sa-appoint-modal .sa-service-card__time {
	font-size: 11.5px;
	color: var(--text-muted, #5e7a90);
}

#create_appoint.sa-appoint-modal .sa-service-card__price {
	flex-shrink: 0;
	font-size: 12px;
	font-weight: 700;
	color: var(--success, #1e7a52);
	background: rgba(30, 122, 82, 0.1);
	border-radius: 999px;
	padding: 3px 8px;
	white-space: nowrap;
}

/* Consultant Tom Select — no shadow */
#create_appoint.sa-appoint-modal .ts-wrapper,
#create_appoint.sa-appoint-modal .ts-wrapper.single,
#create_appoint.sa-appoint-modal .ts-wrapper.form-control {
	width: 100% !important;
	padding: 0 !important;
	border: 0 !important;
	background: transparent !important;
	box-shadow: none !important;
}

#create_appoint.sa-appoint-modal .ts-wrapper .ts-control,
#create_appoint.sa-appoint-modal .ts-wrapper.focus .ts-control,
#create_appoint.sa-appoint-modal .ts-wrapper.input-active .ts-control,
#create_appoint.sa-appoint-modal .ts-wrapper.dropdown-active .ts-control {
	min-height: 38px;
	border-radius: 8px !important;
	border: 1px solid var(--border, #c8dcef) !important;
	background: var(--card-bg, #fff) !important;
	box-shadow: none !important;
	outline: none !important;
}

#create_appoint.sa-appoint-modal .ts-wrapper.focus .ts-control,
#create_appoint.sa-appoint-modal .ts-wrapper.input-active .ts-control {
	border-color: var(--sidebar-active, #3a6fa8) !important;
}

#create_appoint.sa-appoint-modal .ts-wrapper .ts-control > input {
	box-shadow: none !important;
	outline: none !important;
	width: 100% !important;
	min-width: 10rem !important;
	opacity: 1 !important;
}

/* Toggle switches */
#create_appoint.sa-appoint-modal .sa-toggle-row {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 0;
	background: transparent;
	border: 0;
}

#create_appoint.sa-appoint-modal .sa-switch {
	display: flex;
	align-items: center;
	gap: 12px;
	margin: 0;
	padding: 10px 12px;
	border: 1px solid var(--border, #c8dcef);
	border-radius: 10px;
	background: var(--page-bg, #f0f6ff);
	cursor: pointer;
	user-select: none;
}

#create_appoint.sa-appoint-modal .sa-switch:has(.sa-switch__input:checked) {
	background: rgba(58, 111, 168, 0.1);
	border-color: var(--sidebar-active, #3a6fa8);
}

#create_appoint.sa-appoint-modal .sa-switch__input {
	position: absolute;
	opacity: 0;
	width: 1px;
	height: 1px;
	pointer-events: none;
}

#create_appoint.sa-appoint-modal .sa-switch__track {
	position: relative;
	flex-shrink: 0;
	width: 42px;
	height: 24px;
	border-radius: 999px;
	background: #c5d4e4;
	border: 1px solid #a8bdd0;
	transition: background-color 0.18s ease, border-color 0.18s ease;
}

#create_appoint.sa-appoint-modal .sa-switch__knob {
	position: absolute;
	top: 2px;
	left: 2px;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	background: #fff;
	box-shadow: 0 1px 3px rgba(26, 44, 64, 0.28);
	transition: transform 0.18s ease;
}

#create_appoint.sa-appoint-modal .sa-switch__input:checked + .sa-switch__track {
	background: var(--sidebar-active, #3a6fa8);
	border-color: var(--sidebar-active, #3a6fa8);
}

#create_appoint.sa-appoint-modal .sa-switch__input:checked + .sa-switch__track .sa-switch__knob {
	transform: translateX(18px);
}

#create_appoint.sa-appoint-modal .sa-switch__copy {
	display: flex;
	flex-direction: column;
	gap: 1px;
	min-width: 0;
}

#create_appoint.sa-appoint-modal .sa-switch__title {
	font-size: 13px;
	font-weight: 700;
	color: var(--navy, #1e3d60);
}

#create_appoint.sa-appoint-modal .sa-switch__hint {
	font-size: 11.5px;
	color: var(--text-muted, #5e7a90);
}

/* Date/time panel */
#create_appoint.sa-appoint-modal .modern-datetime-container {
	border: 1px solid var(--border, #c8dcef);
	border-radius: 10px;
	background: #fff;
	box-shadow: none;
	overflow: hidden;
}

#create_appoint.sa-appoint-modal .datetime-content {
	display: flex;
	min-height: 280px;
}

#create_appoint.sa-appoint-modal .calendar-section {
	flex: 0 0 45%;
	padding: 12px;
	border-right: 1px solid var(--border, #c8dcef);
	background: var(--page-bg, #f0f6ff);
}

#create_appoint.sa-appoint-modal .timeslot-section {
	flex: 0 0 55%;
	padding: 12px;
	background: #fff;
}

#create_appoint.sa-appoint-modal .section-header {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 10px;
	padding-bottom: 8px;
	border-bottom: 1px solid var(--border, #c8dcef);
}

#create_appoint.sa-appoint-modal .section-header i {
	color: var(--sidebar-active, #3a6fa8);
	font-size: 14px;
}

#create_appoint.sa-appoint-modal .section-header span {
	font-weight: 700;
	font-size: 13px;
	color: var(--navy, #1e3d60);
}

#create_appoint.sa-appoint-modal .calendar-wrapper {
	padding: 4px;
	min-height: 240px;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-calendar {
	border: none;
	background: transparent;
	padding: 0;
	box-shadow: none;
	width: 100%;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-month {
	background: var(--navy, #1e3d60);
	color: #fff;
	border-radius: 8px 8px 0 0;
	padding: 6px 0;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-current-month,
#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-current-month .cur-month,
#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-current-month .cur-year,
#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-current-month .numInputWrapper {
	color: #fff;
	font-weight: 700;
	font-size: 13px;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-prev-month,
#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-next-month {
	color: #fff !important;
	fill: #fff !important;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-weekday {
	color: var(--navy, #1e3d60);
	font-weight: 700;
	font-size: 11px;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-day {
	border-radius: 6px;
	border: none;
	font-weight: 500;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-day:hover,
#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-day.inRange {
	background: rgba(58, 111, 168, 0.15);
	color: var(--navy, #1e3d60);
	border-color: transparent;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-day.selected,
#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-day.selected:hover {
	background: var(--sidebar-active, #3a6fa8);
	border-color: var(--sidebar-active, #3a6fa8);
	color: #fff;
	box-shadow: none;
}

#create_appoint.sa-appoint-modal .calendar-wrapper .flatpickr-day.today:not(.selected) {
	color: var(--sidebar-active, #3a6fa8);
	font-weight: 700;
}

#create_appoint.sa-appoint-modal .timeslot-wrapper {
	min-height: 240px;
	position: relative;
}

#create_appoint.sa-appoint-modal .selected-date-display {
	background: var(--navy, #1e3d60);
	color: #fff;
	padding: 10px 12px;
	border-radius: 8px;
	margin-bottom: 10px;
	display: flex;
	align-items: center;
	gap: 10px;
	box-shadow: none;
}

#create_appoint.sa-appoint-modal .date-icon {
	width: 28px;
	height: 28px;
	font-size: 13px;
	background: rgba(255, 255, 255, 0.18);
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
}

#create_appoint.sa-appoint-modal .date-info .modern-selected-date {
	font-weight: 700;
	font-size: 14px;
	color: #fff;
}

#create_appoint.sa-appoint-modal .date-info .modern-selected-day {
	font-size: 12px;
	color: rgba(255, 255, 255, 0.85);
}

#create_appoint.sa-appoint-modal .timeslots-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 8px;
	max-height: 200px;
	overflow-y: auto;
	padding: 2px;
}

#create_appoint.sa-appoint-modal .timeslots-grid .timeslot {
	border: 1px solid var(--border, #c8dcef);
	border-radius: 8px;
	padding: 8px 10px;
	text-align: center;
	cursor: pointer;
	font-size: 12.5px;
	font-weight: 600;
	color: var(--text-dark, #1a2c40);
	background: #fff;
	box-shadow: none;
	transform: none;
}

#create_appoint.sa-appoint-modal .timeslots-grid .timeslot:hover {
	border-color: var(--sidebar-active, #3a6fa8);
	background: rgba(58, 111, 168, 0.08);
	color: var(--navy, #1e3d60);
}

#create_appoint.sa-appoint-modal .timeslots-grid .timeslot.selected {
	border-color: var(--sidebar-active, #3a6fa8);
	background: var(--sidebar-active, #3a6fa8);
	color: #fff;
	box-shadow: none;
}

#create_appoint.sa-appoint-modal .timeslots-grid .timeslot.disabled {
	color: #adb5bd;
	background: #f8f9fa;
	border-color: #e9ecef;
	cursor: not-allowed;
	opacity: 0.7;
}

#create_appoint.sa-appoint-modal .no-slots-message {
	text-align: center;
	padding: 28px 16px;
	border: 1px dashed var(--border, #c8dcef);
	border-radius: 8px;
	background: var(--page-bg, #f0f6ff);
}

#create_appoint.sa-appoint-modal .no-slots-icon {
	color: var(--text-muted, #5e7a90);
	font-size: 28px;
	margin-bottom: 8px;
}

#create_appoint.sa-appoint-modal .no-slots-text h6 {
	color: var(--navy, #1e3d60);
	font-weight: 700;
	margin-bottom: 4px;
	font-size: 14px;
}

#create_appoint.sa-appoint-modal .no-slots-text p {
	color: var(--text-muted, #5e7a90);
	font-size: 12.5px;
	margin: 0;
}

#create_appoint.sa-appoint-modal .timeslot-wrapper .no-slots-message {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	width: calc(100% - 16px);
}

#create_appoint.sa-appoint-modal .sa-appoint-modal__footer,
#create_appoint.sa-appoint-modal .appointment-modal-actions-row {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 8px;
	margin-top: 10px;
	padding: 12px 0 4px;
	border-top: 1px solid var(--border, #c8dcef);
}

#create_appoint.sa-appoint-modal .sa-appoint-modal__footer .btn {
	border-radius: 8px;
	font-weight: 600;
	min-height: 36px;
	padding: 0.4rem 0.95rem;
}

@media (max-width: 768px) {
	#create_appoint.sa-appoint-modal .sa-service-grid {
		grid-template-columns: 1fr;
	}

	#create_appoint.sa-appoint-modal .datetime-content {
		flex-direction: column;
	}

	#create_appoint.sa-appoint-modal .calendar-section {
		flex: none;
		border-right: none;
		border-bottom: 1px solid var(--border, #c8dcef);
	}

	#create_appoint.sa-appoint-modal .timeslot-section {
		flex: none;
	}
}
</style>

<script>
// Service selection functionality
function selectService(serviceId) {
	// Remove selected class from all cards
	document.querySelectorAll('.service-card-compact').forEach(card => {
		card.classList.remove('selected');
	});
	
	// Add selected class to clicked card
	event.currentTarget.classList.add('selected');
	
	// Check the radio button
	var el = document.getElementById('service_' + serviceId);
	if (el) el.checked = true;
	document.getElementById('service_id').value = serviceId;
	
	// Show appointment details section
	if (typeof $ !== 'undefined') {
	$('#appointment_details').show();
	} else {
		document.getElementById('appointment_details').style.display = 'block';
	}
}

// Function to toggle Video Call option visibility based on service selection
function toggleVideoCallOption(serviceId) {
	const videoCallOption = document.getElementById('video_call_option');
	const appointmentDetailsSelect = document.querySelector('.appointment_item');
	
	if (videoCallOption && appointmentDetailsSelect) {
		// promo_free = free slot — hide Video Call; paid / paid_extended = show Video Call
		if (serviceId === 'promo_free') {
			videoCallOption.style.display = 'none';
			if (appointmentDetailsSelect.value === 'video_call') {
				appointmentDetailsSelect.value = '';
			}
		} else if (serviceId === 'paid' || serviceId === 'paid_extended') {
			videoCallOption.style.display = 'block';
		}
	}
}

// Auto-select radio when card is clicked using event delegation
document.addEventListener('DOMContentLoaded', function() {
	// Initialize Video Call option as hidden when modal opens
	$(document).on('shown.bs.modal', '#create_appoint', function() {
		const videoCallOption = document.getElementById('video_call_option');
		if (videoCallOption) {
			videoCallOption.style.display = 'none';
		}
		// Reset appointment details dropdown
		const appointmentDetailsSelect = document.querySelector('.appointment_item');
		if (appointmentDetailsSelect) {
			appointmentDetailsSelect.value = '';
		}
		const consultantSelect = document.getElementById('add_appointment_consultant_id');
		if (consultantSelect) {
			consultantSelect.value = '';
		}
		// Reset Nature of Enquiry and show all services by default
		const enquirySelect = document.querySelector('.enquiry_item');
		if (enquirySelect) {
			enquirySelect.value = '';
		}
		// Show Free Consultation service by default when modal opens
		const promoFreeService = document.querySelector('.service-promo-free');
		if (promoFreeService) {
			promoFreeService.style.display = '';
		}
		// Reset service selection
		document.querySelectorAll('.services_item').forEach(radio => {
			radio.checked = false;
		});
		document.getElementById('service_id').value = '';
		// Remove selected class from all service cards
		document.querySelectorAll('.service-card-compact').forEach(card => {
			card.classList.remove('selected');
		});
		// Reset "Send confirmation email" checkbox to checked (default)
		const sendConfirmationCheckbox = document.getElementById('send_confirmation_email');
		if (sendConfirmationCheckbox) {
			sendConfirmationCheckbox.checked = true;
		}
		// Hide services and appointment sections
		document.getElementById('services').style.display = 'none';
		document.getElementById('appointment_details').style.display = 'none';
		document.getElementById('info').style.display = 'none';
	});
	
	// Reset form when modal is hidden
	$(document).on('hidden.bs.modal', '#create_appoint', function() {
		// Show Free Consultation service by default when modal is closed (for next open)
		const promoFreeService = document.querySelector('.service-promo-free');
		if (promoFreeService) {
			promoFreeService.style.display = '';
		}
	});
	
	// Use event delegation to handle clicks on service cards
	document.addEventListener('click', function(e) {
		if (e.target.closest('.service-card-compact')) {
			const card = e.target.closest('.service-card-compact');
			const serviceId = card.getAttribute('data-service-id');
			
			if (serviceId) {
				// Remove selected class from all cards
				document.querySelectorAll('.service-card-compact').forEach(c => {
					c.classList.remove('selected');
				});
				
				// Add selected class to clicked card
				card.classList.add('selected');
				
				// Check the radio button
				const radio = card.querySelector('input[type="radio"]');
			if (radio) {
				radio.checked = true;
				document.getElementById('service_id').value = radio.value;
				// Programmatic check does not fire change in many browsers — jQuery handlers never run (calendar/slots won't load).
				if (typeof jQuery !== 'undefined') {
					jQuery(radio).trigger('change');
				} else {
					radio.dispatchEvent(new Event('change', { bubbles: true }));
				}
			}
				
				// Toggle Video Call option visibility based on service
				toggleVideoCallOption(serviceId);
				
				// Show appointment details section
				if (typeof $ !== 'undefined') {
					$('#appointment_details').show();
				} else {
					document.getElementById('appointment_details').style.display = 'block';
				}
			}
		}
	});

	// Handle Nature of Enquiry selection
	document.addEventListener('change', function(e) {
		if (e.target.classList.contains('enquiry_item')) {
			var selectedValue = e.target.value;
			if (selectedValue) {
				document.getElementById('services').style.display = 'block';
				
				const promoFreeService = document.querySelector('.service-promo-free');
				if (promoFreeService) {
					promoFreeService.style.display = '';
				}
			} else {
				document.getElementById('services').style.display = 'none';
				document.getElementById('appointment_details').style.display = 'none';
				document.getElementById('info').style.display = 'none';
			}
		}
		
		if (e.target.classList.contains('services_item')) {
			if (e.target.checked) {
				const serviceId = e.target.value;
				// Toggle Video Call option visibility based on service
				toggleVideoCallOption(serviceId);
				document.getElementById('appointment_details').style.display = 'block';
			}
		}
		
		if (e.target.classList.contains('appointment_item')) {
			var selectedValue = e.target.value;
		if (selectedValue) {
				document.getElementById('info').style.display = 'block';
		} else {
				document.getElementById('info').style.display = 'none';
			}
		}
	});

	// Modern Appointment Booking Enhancement System
	(function() {
		'use strict';
		
		let timeslotObserver = null;
		let dateObserver = null;
		
		// Function to enhance timeslots with modern design
		function enhanceTimeslots() {
			const oldTimeslots = document.querySelector('.timeslots');
			const modernGrid = document.querySelector('.timeslots-grid');
			const noSlotsMsg = document.querySelector('.no-slots-message');
			
			if (!oldTimeslots || !modernGrid) return;
			
			// Get all timeslot_col elements (correct class name from detail-main.js)
			const oldSlots = oldTimeslots.querySelectorAll('.timeslot_col');
			
			if (oldSlots.length > 0) {
				// Clear modern grid
				modernGrid.innerHTML = '';
				modernGrid.style.display = 'grid';
				modernGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';
				modernGrid.style.gap = '10px';
				if (noSlotsMsg) noSlotsMsg.style.display = 'none';
				
				// Copy each slot to modern grid
				oldSlots.forEach((oldSlot) => {
					const modernSlot = document.createElement('div');
					modernSlot.className = 'timeslot';
					
					// Extract time text
					const timeText = oldSlot.querySelector('span') ? 
						oldSlot.querySelector('span').textContent : 
						oldSlot.textContent;
					
					modernSlot.textContent = timeText;
					modernSlot.dataset.fromtime = oldSlot.dataset.fromtime;
					modernSlot.dataset.totime = oldSlot.dataset.totime;
					
					// Add click handler
					modernSlot.addEventListener('click', function() {
						// Remove selected from all modern slots
						modernGrid.querySelectorAll('.timeslot').forEach(s => 
							s.classList.remove('selected')
						);
						
						// Add selected to this slot
						this.classList.add('selected');
						
						// Click the corresponding old slot
						oldSlot.click();
					});
					
					// Check if old slot has active class
					if (oldSlot.classList.contains('active') || 
					    oldSlot.classList.contains('selected')) {
						modernSlot.classList.add('selected');
					}
					
					modernGrid.appendChild(modernSlot);
				});
			} else {
				// No slots available
				modernGrid.innerHTML = '';
				modernGrid.style.display = 'none';
				if (noSlotsMsg) noSlotsMsg.style.display = 'block';
			}
		}
		
		// Function to update modern date display
		function updateDateDisplay() {
			const oldDateDisplay = document.querySelector('.showselecteddate');
			const modernDate = document.querySelector('.modern-selected-date');
			const modernDay = document.querySelector('.modern-selected-day');
			
			if (!oldDateDisplay || !modernDate || !modernDay) return;
			
			const dateText = oldDateDisplay.textContent.trim();
			
			if (dateText) {
				modernDate.textContent = dateText;
				modernDay.textContent = 'Selected Date';
			} else {
				modernDate.textContent = 'Select a date';
				modernDay.textContent = 'from the calendar';
			}
		}
		
		// Initialize MutationObserver for timeslots
		function initTimeslotObserver() {
			const oldTimeslots = document.querySelector('.timeslots');
			if (!oldTimeslots) return;
			
			timeslotObserver = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.type === 'childList') {
						enhanceTimeslots();
					}
				});
			});
			
			timeslotObserver.observe(oldTimeslots, {
				childList: true,
				subtree: true
			});
		}
		
		// Initialize MutationObserver for date display
		function initDateObserver() {
			const oldDateDisplay = document.querySelector('.showselecteddate');
			if (!oldDateDisplay) return;
			
			dateObserver = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.type === 'childList' || mutation.type === 'characterData') {
						updateDateDisplay();
					}
				});
			});
			
			dateObserver.observe(oldDateDisplay, {
				childList: true,
				characterData: true,
				subtree: true
			});
		}
		
		// Initialize everything when DOM is ready
		function init() {
			// Initial enhancement
			enhanceTimeslots();
			updateDateDisplay();
			
			// Start observing
			initTimeslotObserver();
			initDateObserver();
			
			// Also enhance on AJAX complete (catches datepicker init)
			if (typeof $ !== 'undefined') {
				$(document).ajaxSuccess(function(event, xhr, settings) {
					if (settings.url && (
						settings.url.includes('getDateTimeBackend') || 
						settings.url.includes('getDisabledDateTime')
					)) {
						setTimeout(function() {
							enhanceTimeslots();
							updateDateDisplay();
						}, 100);
					}
				});
			}
		}
		
		// Start when DOM is ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
		
		// Also try init after a short delay (for modal load)
		setTimeout(init, 500);
		
	})();
});
</script>

