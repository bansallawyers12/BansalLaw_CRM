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
            <div class="invoice-line-card__grid">
                <div class="invoice-field invoice-col-work-date">
                    <label class="invoice-field__label">Work date</label>
                    <div class="invoice-date-cell">
                        <select class="form-select form-select-sm invoice-date-mode-select" aria-label="Date or range" title="One day or a date range">
                            <option value="single" selected>One day</option>
                            <option value="range">Date range</option>
                        </select>
                        <input type="hidden" name="invoice_date_mode[]" class="invoice-date-mode" value="single" />
                        <input data-valid="required" class="form-control form-control-sm invoice-work-date" name="trans_date[]" type="text" value="" readonly="readonly" placeholder="Select date" title="Choose a date or range from the calendar" />
                    </div>
                </div>

                <div class="invoice-field invoice-col-recorded">
                    <label class="invoice-field__label">Recorded</label>
                    <input data-valid="required" class="form-control form-control-sm report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" readonly="readonly" title="Date this entry was posted in the system" />
                </div>

                @if($includeTransNo)
                    <div class="invoice-field invoice-col-trans">
                        <label class="invoice-field__label">Trans. No</label>
                        <input class="form-control form-control-sm unique_trans_no_invoice" type="text" value="" readonly/>
                        <input class="unique_trans_no_hidden_invoice" name="trans_no[]" type="hidden" value="" />
                    </div>
                @endif

                <div class="invoice-field invoice-col-type">
                    <label class="invoice-field__label">Type</label>
                    <select class="form-select form-select-sm payment_type_invoice_per_row payment_type_cls" name="payment_type[]" data-valid="required">
                        <option value="">Select type</option>
                        @foreach(\App\Support\InvoiceChargeTypes::options() as $chargeType)
                            <option value="{{ $chargeType }}">{{ $chargeType }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="invoice-field invoice-col-earner">
                    <label class="invoice-field__label">Fee earner</label>
                    <select class="form-select form-select-sm invoice-fee-earner" name="fee_earner_id[]">
                        <option value="">Select</option>
                        @foreach($feeEarners as $earner)
                            <option value="{{ $earner->id }}">{{ trim(($earner->first_name ?? '').' '.($earner->last_name ?? '')) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="invoice-field invoice-col-role">
                    <label class="invoice-field__label">Role</label>
                    <select class="form-select form-select-sm invoice-fee-earner-role" name="fee_earner_role[]">
                        <option value="">Select</option>
                        @foreach(\App\Support\InvoiceTimesheetLine::roles() as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="invoice-field invoice-hours-col invoice-col-hrs">
                    <label class="invoice-field__label">Hrs</label>
                    <input class="form-control form-control-sm invoice-hours" name="hours[]" type="text" inputmode="decimal" placeholder="" title="Hours worked" />
                </div>

                <div class="invoice-field invoice-col-rate">
                    <label class="invoice-field__label">Rate <span class="invoice-field__sub">(ex GST)</span></label>
                    <input class="form-control form-control-sm invoice-rate-ex-gst" name="rate_ex_gst[]" type="text" inputmode="decimal" placeholder="" title="Hourly rate excluding GST" />
                </div>

                <div class="invoice-field invoice-col-amount">
                    <label class="invoice-field__label">Amount <span class="invoice-field__sub">(ex GST)</span></label>
                    <input data-valid="required" class="form-control form-control-sm invoice-amount-ex-gst" name="amount_ex_gst[]" type="text" inputmode="decimal" value="" title="Amount excluding GST" />
                </div>

                <div class="invoice-field invoice-col-gst">
                    <label class="invoice-field__label">GST</label>
                    <input class="form-control form-control-sm invoice-line-gst" name="line_gst[]" type="text" inputmode="decimal" value="" title="GST for this line" />
                </div>

                <div class="invoice-field invoice-col-actions">
                    <label class="invoice-field__label">&nbsp;</label>
                    <a class="removeitems_invoice invoice-line-remove" href="javascript:;" title="Remove line" aria-label="Remove line"><i class="fa-solid fa-xmark"></i></a>
                </div>
            </div>

            <div class="invoice-field invoice-col-desc">
                <label class="invoice-field__label" for="">Description</label>
                <textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="3" placeholder="Describe the work for this line…"></textarea>
            </div>
        </div>
    </td>
</tr>
