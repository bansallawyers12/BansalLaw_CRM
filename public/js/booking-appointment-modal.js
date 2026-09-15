/**
 * Shared website booking appointment modal (dashboard).
 * Expects #eventModal / #cancellationConfirmModal from event-modals partial,
 * window.consultantsData, and window.BOOKING_WEB_BASE.
 */
(function (window, document) {
    'use strict';

    var pendingCancel = null;

    function bookingBase() {
        return String(window.BOOKING_WEB_BASE || '/booking').replace(/\/$/, '');
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showToast(type, message) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type === 'danger' ? 'error' : type);
            return;
        }
        if (typeof window.showAlert === 'function') {
            window.showAlert(type, message);
            return;
        }
        console.log('[' + type + ']', message);
    }

    function parseDate(value) {
        if (!value) return null;
        if (value instanceof Date) return isNaN(value.getTime()) ? null : value;
        var d = new Date(value);
        return isNaN(d.getTime()) ? null : d;
    }

    function formatWhen(iso) {
        var d = parseDate(iso);
        if (!d) return 'N/A';
        try {
            return d.toLocaleString(undefined, {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
        } catch (e) {
            return d.toISOString();
        }
    }

    function melbourneParts(iso) {
        var d = parseDate(iso) || new Date();
        var date = d.toLocaleDateString('en-CA', { timeZone: 'Australia/Melbourne' });
        var time = d.toLocaleTimeString('en-GB', {
            timeZone: 'Australia/Melbourne',
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
        });
        return { date: date, time: time };
    }

    function durationMinutes(props) {
        if (props.duration_minutes) return Math.max(15, parseInt(props.duration_minutes, 10) || 15);
        var start = parseDate(props.starts_at || props.appointment_datetime);
        var end = parseDate(props.ends_at);
        if (start && end) {
            var mins = Math.round((end.getTime() - start.getTime()) / 60000);
            return mins > 0 ? mins : 15;
        }
        return 15;
    }

    function detailItem(icon, label, valueHtml) {
        return (
            '<div class="appt-detail-item">' +
            '<div class="appt-detail-item__icon"><i class="fa-solid ' + icon + '"></i></div>' +
            '<div class="appt-detail-item__content">' +
            '<span class="appt-detail-item__label">' + escapeHtml(label) + '</span>' +
            '<div class="appt-detail-item__value">' + valueHtml + '</div>' +
            '</div></div>'
        );
    }

    function statusPill(status, label) {
        var key = String(status || 'pending').toLowerCase().replace(/[^a-z0-9]+/g, '_');
        return (
            '<span class="appt-status-pill appt-status-pill--' + key + '" id="statusBadge">' +
            escapeHtml(label || String(status || '').toUpperCase()) +
            '</span>'
        );
    }

    function normalizeProps(raw) {
        var props = Object.assign({}, raw || {});
        props.booking_appointment_id = props.booking_appointment_id || props.id || null;
        if (typeof props.booking_appointment_id === 'string' && props.booking_appointment_id.indexOf('booking-') === 0) {
            props.booking_appointment_id = parseInt(props.booking_appointment_id.replace('booking-', ''), 10);
        }
        props.client_name = props.client_name || props.title || 'Client';
        props.status = props.status || 'pending';
        props.status_label = props.status_label || String(props.status).replace(/_/g, ' ');
        props.meeting_type = props.meeting_type || 'in_person';
        props.preferred_language = props.preferred_language || 'English';
        props.is_paid = !!props.is_paid;
        props.payment_status = props.payment_status || (props.is_paid ? 'Paid' : 'Free');
        props.consultant = props.consultant || props.consultant_name || '';
        if (props.consultant && typeof props.consultant === 'object') {
            props.consultant_id = props.consultant_id || props.consultant.id;
            props.consultant = props.consultant.name || '';
        }
        return props;
    }

    function setHeader(props) {
        var titleEl = document.getElementById('eventModalTitle');
        var subtitleEl = document.getElementById('eventModalSubtitle');
        var iconEl = document.getElementById('eventModalIcon');
        if (titleEl) titleEl.textContent = 'Appointment Details';
        if (subtitleEl) {
            var sub = props.service_type || props.client_name || '';
            subtitleEl.textContent = sub;
            subtitleEl.classList.toggle('d-none', !sub);
        }
        if (iconEl) {
            iconEl.innerHTML = '<i class="fa-solid fa-calendar-check"></i>';
            iconEl.style.background = '#1e3d60';
            iconEl.style.color = '#fff';
        }
        var editBtn = document.getElementById('courtHearingEditBtn');
        var saveBtn = document.getElementById('courtHearingSaveBtn');
        var cancelEditBtn = document.getElementById('courtHearingCancelEditBtn');
        if (editBtn) editBtn.classList.add('d-none');
        if (saveBtn) saveBtn.classList.add('d-none');
        if (cancelEditBtn) cancelEditBtn.classList.add('d-none');
    }

    function consultantOptionsHtml(props, slotKey) {
        var list = Array.isArray(window.consultantsData) ? window.consultantsData : [];
        var seen = {};
        var html = '<option value="">Select consultant…</option>';
        list.forEach(function (c) {
            if (!c || !c.id || seen[c.id]) return;
            seen[c.id] = true;
            var selected = false;
            if (props.consultant_id && Number(props.consultant_id) === Number(c.id)) selected = true;
            else if (props.consultant && String(props.consultant).indexOf(c.name) !== -1) selected = true;
            html +=
                '<option value="' +
                escapeHtml(String(c.id)) +
                '"' +
                (selected ? ' selected' : '') +
                '>' +
                escapeHtml(c.name) +
                ' (' +
                escapeHtml(c.calendar_type || '') +
                ')</option>';
        });
        return html;
    }

    function renderBody(props) {
        var manageId = parseInt(props.booking_appointment_id, 10);
        var canManage = !isNaN(manageId) && manageId > 0;
        var slotKey = 'dash-' + (canManage ? manageId : 'x');
        var when = melbourneParts(props.starts_at || props.appointment_datetime);
        var duration = durationMinutes(props);
        var meetingLabel = props.meeting_type_label || String(props.meeting_type || '').replace(/_/g, ' ');
        meetingLabel = meetingLabel ? meetingLabel.charAt(0).toUpperCase() + meetingLabel.slice(1) : 'N/A';

        var management = canManage
            ? '<section class="appt-detail-section">' +
              '<h6 class="appt-detail-section__title"><i class="fa-solid fa-calendar-days"></i> Reschedule</h6>' +
              '<div class="row g-3 align-items-end">' +
              '<div class="col-md-4"><label class="form-label">Appointment date</label>' +
              '<input type="date" class="form-control" id="rescheduleDate-' +
              slotKey +
              '" value="' +
              escapeHtml(when.date) +
              '"></div>' +
              '<div class="col-md-4"><label class="form-label">Appointment time</label>' +
              '<input type="time" class="form-control" id="rescheduleTime-' +
              slotKey +
              '" value="' +
              escapeHtml(when.time) +
              '"></div>' +
              '<div class="col-md-4"><button type="button" class="btn btn-primary w-100" data-bam-action="reschedule" data-id="' +
              manageId +
              '" data-slot="' +
              slotKey +
              '" data-meeting-type="' +
              escapeHtml(props.meeting_type || 'in_person') +
              '" data-language="' +
              escapeHtml(props.preferred_language || 'English') +
              '"><i class="fa-solid fa-floppy-disk"></i> Update Date &amp; Time</button></div></div></section>' +
              '<div class="row g-3"><div class="col-md-6"><section class="appt-detail-section h-100">' +
              '<h6 class="appt-detail-section__title"><i class="fa-solid fa-pen-to-square"></i> Change status</h6>' +
              '<div class="appt-action-buttons">' +
              '<button type="button" class="btn btn-sm btn-outline-success" data-bam-action="status" data-id="' +
              manageId +
              '" data-status="confirmed"><i class="fa-solid fa-check"></i> Confirmed</button>' +
              '<button type="button" class="btn btn-sm btn-outline-primary" data-bam-action="status" data-id="' +
              manageId +
              '" data-status="completed"><i class="fa-solid fa-circle-check"></i> Complete</button>' +
              (props.final_amount && parseFloat(props.final_amount) > 0
                  ? '<button type="button" class="btn btn-sm btn-outline-info" data-bam-action="status" data-id="' +
                    manageId +
                    '" data-status="paid"><i class="fa-solid fa-dollar-sign"></i> Payment done</button>' +
                    '<button type="button" class="btn btn-sm btn-outline-warning" data-bam-action="status" data-id="' +
                    manageId +
                    '" data-status="pending"><i class="fa-solid fa-clock"></i> Payment pending</button>'
                  : '') +
              '<button type="button" class="btn btn-sm btn-outline-danger" data-bam-action="status" data-id="' +
              manageId +
              '" data-status="cancelled"><i class="fa-solid fa-xmark"></i> Cancelled</button>' +
              '<button type="button" class="btn btn-sm btn-outline-secondary" data-bam-action="status" data-id="' +
              manageId +
              '" data-status="no_show"><i class="fa-solid fa-user-times"></i> No show</button>' +
              '</div></section></div>' +
              '<div class="col-md-6"><section class="appt-detail-section h-100">' +
              '<h6 class="appt-detail-section__title"><i class="fa-solid fa-right-left"></i> Change calendar</h6>' +
              '<label class="form-label">Consultant</label>' +
              '<select class="form-select" id="consultantSelect-' +
              slotKey +
              '" data-bam-action="consultant" data-id="' +
              manageId +
              '" data-slot="' +
              slotKey +
              '">' +
              consultantOptionsHtml(props, slotKey) +
              '</select>' +
              '<div class="form-text">Moves this appointment to the selected calendar.</div></section></div></div>'
            : '<div class="appt-detail-tip"><i class="fa-solid fa-circle-info"></i><span>CRM actions need a synced local appointment id.</span></div>';

        return (
            '<div class="appt-detail-view">' +
            '<div class="appt-detail-hero appt-detail-hero--booking">' +
            '<div class="appt-detail-hero__main">' +
            '<div class="appt-detail-hero__client">' +
            escapeHtml(props.client_name) +
            '</div>' +
            '<div class="appt-detail-hero__when"><i class="fa-solid fa-clock"></i> ' +
            escapeHtml(formatWhen(props.starts_at || props.appointment_datetime)) +
            ' · ' +
            duration +
            ' min</div></div>' +
            '<div class="appt-detail-hero__meta">' +
            statusPill(props.status, props.status_label) +
            '<span class="appt-status-pill appt-status-pill--payment appt-status-pill--payment--' +
            (props.is_paid ? 'paid' : 'unpaid') +
            '">' +
            escapeHtml(props.payment_status) +
            '</span></div></div>' +
            '<div class="appt-detail-grid">' +
            detailItem('fa-envelope', 'Email', escapeHtml(props.client_email || 'N/A')) +
            detailItem('fa-phone', 'Phone', escapeHtml(props.client_phone || 'N/A')) +
            detailItem('fa-briefcase', 'Service', escapeHtml(props.service_type || 'N/A')) +
            detailItem('fa-location-dot', 'Location', escapeHtml(props.location || 'N/A')) +
            detailItem('fa-video', 'Meeting type', escapeHtml(meetingLabel)) +
            detailItem('fa-language', 'Language', escapeHtml(props.preferred_language || 'English')) +
            detailItem('fa-user-tie', 'Consultant', escapeHtml(props.consultant || 'N/A')) +
            (props.is_paid
                ? detailItem(
                      'fa-dollar-sign',
                      'Amount',
                      '$' + (props.final_amount ? parseFloat(props.final_amount).toFixed(2) : '0.00')
                  )
                : '') +
            '</div>' +
            management +
            '</div>'
        );
    }

    function showModal() {
        var el = document.getElementById('eventModal');
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(el).show();
            return;
        }
        if (window.jQuery) {
            window.jQuery(el).modal('show');
        }
    }

    function hideModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (window.bootstrap && window.bootstrap.Modal) {
            var inst = window.bootstrap.Modal.getInstance(el);
            if (inst) inst.hide();
            return;
        }
        if (window.jQuery) {
            window.jQuery(el).modal('hide');
        }
    }

    function notifyChanged() {
        document.dispatchEvent(new CustomEvent('booking-appointment-updated'));
        if (window.staffDashboardCalendar && typeof window.staffDashboardCalendar.refetchEvents === 'function') {
            window.staffDashboardCalendar.refetchEvents();
        }
    }

    function openFromProps(raw) {
        var props = normalizeProps(raw);
        var body = document.getElementById('eventModalBody');
        if (!body) {
            showToast('danger', 'Appointment modal is not available on this page.');
            return;
        }
        setHeader(props);
        body.innerHTML = renderBody(props);

        var vfd = document.getElementById('viewFullDetails');
        var manageId = parseInt(props.booking_appointment_id, 10);
        if (vfd) {
            if (!isNaN(manageId) && manageId > 0) {
                vfd.classList.remove('d-none');
                vfd.href = bookingBase() + '/appointments/' + manageId;
                vfd.innerHTML = '<i class="fa-solid fa-arrow-up-right-from-square"></i> View Full Details';
            } else {
                vfd.classList.add('d-none');
            }
        }
        showModal();
    }

    function openById(id) {
        var appointmentId = parseInt(id, 10);
        if (isNaN(appointmentId) || appointmentId < 1) return;
        fetch(bookingBase() + '/appointments/' + appointmentId + '/json', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data) {
                    throw new Error((payload && payload.message) || 'Failed to load appointment');
                }
                var d = payload.data;
                openFromProps({
                    booking_appointment_id: d.id,
                    client_name: d.client_name,
                    client_email: d.client_email,
                    client_phone: d.client_phone,
                    appointment_datetime: d.appointment_datetime,
                    starts_at: d.appointment_datetime,
                    location: d.location,
                    service_type: d.service_type,
                    meeting_type: d.meeting_type,
                    preferred_language: d.preferred_language || 'English',
                    status: d.status,
                    is_paid: d.is_paid,
                    final_amount: d.final_amount,
                    payment_status: d.payment_status,
                    consultant: d.consultant,
                    consultant_id: d.consultant && d.consultant.id,
                    duration_minutes: d.duration_minutes,
                });
            })
            .catch(function (err) {
                showToast('danger', err.message || 'Failed to load appointment');
            });
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body || {}),
        }).then(async function (response) {
            var data = {};
            try {
                data = await response.json();
            } catch (e) {
                data = {};
            }
            if (!response.ok || data.success === false) {
                throw new Error(data.message || data.error || 'Request failed (HTTP ' + response.status + ')');
            }
            return data;
        });
    }

    function updateStatus(appointmentId, status, buttonEl) {
        if (status === 'cancelled') {
            pendingCancel = { id: appointmentId, button: buttonEl };
            var input = document.getElementById('cancelReasonInput');
            var err = document.getElementById('cancelReasonError');
            if (input) input.value = '';
            if (err) err.classList.add('d-none');
            var cancelModal = document.getElementById('cancellationConfirmModal');
            if (cancelModal && window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(cancelModal).show();
            } else if (window.jQuery && cancelModal) {
                window.jQuery(cancelModal).modal('show');
            }
            return;
        }

        var original = buttonEl ? buttonEl.innerHTML : '';
        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }

        postJson(bookingBase() + '/appointments/' + appointmentId + '/update-status', { status: status })
            .then(function () {
                showToast('success', 'Status updated');
                hideModal('eventModal');
                notifyChanged();
            })
            .catch(function (err) {
                showToast('danger', err.message || 'Failed to update status');
            })
            .finally(function () {
                if (buttonEl) {
                    buttonEl.disabled = false;
                    buttonEl.innerHTML = original;
                }
            });
    }

    function confirmCancellation() {
        if (!pendingCancel) return;
        var input = document.getElementById('cancelReasonInput');
        var err = document.getElementById('cancelReasonError');
        var reason = input ? String(input.value || '').trim() : '';
        if (!reason) {
            if (err) err.classList.remove('d-none');
            return;
        }
        if (err) err.classList.add('d-none');
        var sendEmail = !!(document.getElementById('sendCancellationEmailCheck') || {}).checked;
        var id = pendingCancel.id;
        var buttonEl = pendingCancel.button;
        pendingCancel = null;
        hideModal('cancellationConfirmModal');

        var body = {
            status: 'cancelled',
            cancellation_reason: reason,
        };
        if (sendEmail) body.send_cancellation_confirmation = true;

        postJson(bookingBase() + '/appointments/' + id + '/update-status', body)
            .then(function () {
                showToast('success', 'Appointment cancelled');
                hideModal('eventModal');
                notifyChanged();
            })
            .catch(function (err) {
                showToast('danger', err.message || 'Failed to cancel');
            });
        if (buttonEl) {
            /* no-op restore */
        }
    }

    function updateConsultant(appointmentId, consultantId) {
        if (!consultantId) return;
        postJson(bookingBase() + '/appointments/' + appointmentId + '/update-consultant', {
            consultant_id: parseInt(consultantId, 10),
        })
            .then(function () {
                showToast('success', 'Consultant updated');
                hideModal('eventModal');
                notifyChanged();
            })
            .catch(function (err) {
                showToast('danger', err.message || 'Failed to update consultant');
            });
    }

    function reschedule(appointmentId, slotKey, meetingType, preferredLanguage) {
        var dateEl = document.getElementById('rescheduleDate-' + slotKey);
        var timeEl = document.getElementById('rescheduleTime-' + slotKey);
        if (!dateEl || !timeEl) return;
        var date = dateEl.value;
        var time = timeEl.value;
        if (!date || !time) {
            showToast('warning', 'Choose a date and time');
            return;
        }
        var formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('appointment_date', date);
        formData.append('appointment_time', time);
        formData.append('meeting_type', meetingType || 'in_person');
        formData.append('preferred_language', preferredLanguage || 'English');

        fetch(bookingBase() + '/appointments/' + appointmentId, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: formData,
        })
            .then(async function (response) {
                var data = {};
                try {
                    data = await response.json();
                } catch (e) {
                    data = {};
                }
                if (!response.ok || data.success === false) {
                    throw new Error(data.message || 'Failed to reschedule');
                }
                return data;
            })
            .then(function () {
                showToast('success', 'Appointment rescheduled');
                hideModal('eventModal');
                notifyChanged();
            })
            .catch(function (err) {
                showToast('danger', err.message || 'Failed to reschedule');
            });
    }

    function onBodyClick(e) {
        var btn = e.target.closest('[data-bam-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-bam-action');
        var id = parseInt(btn.getAttribute('data-id'), 10);
        if (!id) return;
        if (action === 'status') {
            updateStatus(id, btn.getAttribute('data-status'), btn);
        } else if (action === 'reschedule') {
            reschedule(
                id,
                btn.getAttribute('data-slot'),
                btn.getAttribute('data-meeting-type'),
                btn.getAttribute('data-language')
            );
        }
    }

    function onBodyChange(e) {
        var el = e.target;
        if (!el || el.getAttribute('data-bam-action') !== 'consultant') return;
        var id = parseInt(el.getAttribute('data-id'), 10);
        if (!id) return;
        updateConsultant(id, el.value);
    }

    function bindOnce() {
        var body = document.getElementById('eventModalBody');
        if (body && !body.dataset.bamBound) {
            body.dataset.bamBound = '1';
            body.addEventListener('click', onBodyClick);
            body.addEventListener('change', onBodyChange);
        }
        var confirmBtn = document.getElementById('confirmCancelBtn');
        if (confirmBtn && !confirmBtn.dataset.bamBound) {
            confirmBtn.dataset.bamBound = '1';
            confirmBtn.addEventListener('click', confirmCancellation);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindOnce);
    } else {
        bindOnce();
    }

    window.BookingAppointmentModal = {
        openFromProps: openFromProps,
        openById: openById,
    };
})(window, document);
