@php
    $includeTransNo = $includeTransNo ?? false;
    $feeEarners = $feeEarners ?? \App\Support\InvoiceTimesheetLine::selectableFeeEarners();
@endphp
<tr class="clonedrow_invoice invoice-line-block">
    <td class="invoice-line-block__cell">
        <input name="id[]" type="hidden" value="" />
        <input name="gst_included[]" type="hidden" class="invoice-gst-included" value="Yes" />
        <input name="withdraw_amount[]" type="hidden" class="withdraw_amount_invoice_per_row" value="" />
        <input name="billing_basis[]" type="hidden" class="invoice-billing-basis" value="hourly" />

        <div class="invoice-line-card">
            <div class="invoice-line-card__header">
                <div class="invoice-line-badge">
                    <span class="invoice-line-badge__dot"></span>
                    <span class="invoice-line-badge__text">Line Item</span>
                </div>
                <a class="removeitems_invoice invoice-line-remove" href="javascript:;" title="Remove this line item" aria-label="Remove line">
                    <i class="fa-solid fa-trash-can"></i>
                </a>
            </div>

            <div class="invoice-line-card__grid @if($includeTransNo) has-trans-no @endif">
                <div class="invoice-field invoice-col-work-date">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">Work date</label>
                        <div class="btn-group btn-group-xs invoice-date-mode-pills" role="group" aria-label="Date range mode">
                            <button type="button" class="btn invoice-date-mode-btn btn-primary" data-date-mode="single" title="One day">1 day</button>
                            <button type="button" class="btn invoice-date-mode-btn btn-outline-secondary" data-date-mode="range" title="Date range">Range</button>
                        </div>
                        <input type="hidden" name="invoice_date_mode[]" class="invoice-date-mode" value="single" />
                        <select class="d-none invoice-date-mode-select" aria-hidden="true" tabindex="-1">
                            <option value="single" selected>One day</option>
                            <option value="range">Date range</option>
                        </select>
                    </div>
                    <div class="invoice-input-icon-wrap">
                        <i class="fa-regular fa-calendar-days invoice-input-icon"></i>
                        <input data-valid="required" class="form-control form-control-sm invoice-work-date" name="trans_date[]" type="text" value="" readonly="readonly" placeholder="Select date" title="Choose a date or range from the calendar" />
                    </div>
                </div>

                <div class="invoice-field invoice-col-recorded">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">Recorded</label>
                    </div>
                    <div class="invoice-input-icon-wrap">
                        <i class="fa-regular fa-clock invoice-input-icon"></i>
                        <input data-valid="required" class="form-control form-control-sm report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" readonly="readonly" title="Date this entry was posted in the system" />
                    </div>
                </div>

                @if($includeTransNo)
                    <div class="invoice-field invoice-col-trans">
                        <div class="invoice-field__header">
                            <label class="invoice-field__label">Trans. No</label>
                        </div>
                        <input class="form-control form-control-sm unique_trans_no_invoice" type="text" value="" readonly/>
                        <input class="unique_trans_no_hidden_invoice" name="trans_no[]" type="hidden" value="" />
                    </div>
                @endif

                <div class="invoice-field invoice-col-type">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">Type</label>
                    </div>
                    <select class="form-select form-select-sm payment_type_invoice_per_row payment_type_cls" name="payment_type[]" data-valid="required">
                        <option value="">Select type</option>
                        @foreach(\App\Support\InvoiceChargeTypes::options() as $chargeType)
                            <option value="{{ $chargeType }}">{{ $chargeType }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="invoice-field invoice-col-earner">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">Fee earner</label>
                    </div>
                    <select class="form-select form-select-sm invoice-fee-earner" name="fee_earner_id[]">
                        <option value="">Select</option>
                        @foreach($feeEarners as $earner)
                            <option value="{{ $earner->id }}">{{ trim(($earner->first_name ?? '').' '.($earner->last_name ?? '')) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="invoice-field invoice-col-role">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">Role</label>
                    </div>
                    <select class="form-select form-select-sm invoice-fee-earner-role" name="fee_earner_role[]">
                        <option value="">Select</option>
                        @foreach(\App\Support\InvoiceTimesheetLine::roles() as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="invoice-field invoice-hours-col invoice-col-hrs">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">Hrs</label>
                    </div>
                    <input class="form-control form-control-sm invoice-hours" name="hours[]" type="text" inputmode="decimal" placeholder="0.0" title="Hours worked" />
                </div>

                <div class="invoice-field invoice-col-rate">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">Rate <span class="invoice-field__sub">(ex GST)</span></label>
                    </div>
                    <div class="invoice-input-prefix-wrap">
                        <span class="invoice-input-prefix">$</span>
                        <input class="form-control form-control-sm invoice-rate-ex-gst" name="rate_ex_gst[]" type="text" inputmode="decimal" placeholder="0.00" title="Hourly rate excluding GST" />
                    </div>
                </div>

                <div class="invoice-field invoice-col-amount">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">Amount <span class="invoice-field__sub">(ex GST)</span></label>
                    </div>
                    <div class="invoice-input-prefix-wrap">
                        <span class="invoice-input-prefix">$</span>
                        <input data-valid="required" class="form-control form-control-sm invoice-amount-ex-gst" name="amount_ex_gst[]" type="text" inputmode="decimal" value="" placeholder="0.00" title="Amount excluding GST" />
                    </div>
                </div>

                <div class="invoice-field invoice-col-gst">
                    <div class="invoice-field__header">
                        <label class="invoice-field__label">GST</label>
                    </div>
                    <div class="invoice-input-prefix-wrap">
                        <span class="invoice-input-prefix">$</span>
                        <input class="form-control form-control-sm invoice-line-gst" name="line_gst[]" type="text" inputmode="decimal" value="" placeholder="0.00" title="GST for this line" />
                    </div>
                </div>
            </div>

            <div class="invoice-field invoice-col-desc">
                <div class="invoice-field__header">
                    <label class="invoice-field__label"><i class="fa-regular fa-message text-muted me-1"></i> Description</label>
                </div>
                <textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="2" placeholder="Describe the work for this line (e.g. drafting court submissions, conference with counsel, client consultation)..."></textarea>
            </div>
        </div>
    </td>
</tr>
