@php
    $includeTransNo = $includeTransNo ?? false;
    $feeEarners = $feeEarners ?? collect();
@endphp
<tr class="clonedrow_invoice">
    <td>
        <input name="id[]" type="hidden" value="" />
        <input name="gst_included[]" type="hidden" class="invoice-gst-included" value="Yes" />
        <input name="withdraw_amount[]" type="hidden" class="withdraw_amount_invoice_per_row" value="" />
        <input name="billing_basis[]" type="hidden" class="invoice-billing-basis" value="hourly" />
        <div class="invoice-date-cell">
            <div class="btn-group btn-group-sm invoice-date-mode-toggle" role="group" aria-label="Date or range">
                <button type="button" class="btn btn-primary invoice-date-mode-btn" data-date-mode="single">Date</button>
                <button type="button" class="btn btn-outline-secondary invoice-date-mode-btn" data-date-mode="range">Range</button>
            </div>
            <input type="hidden" name="invoice_date_mode[]" class="invoice-date-mode" value="single" />
            <input data-valid="required" class="form-control invoice-work-date" name="trans_date[]" type="text" value="" readonly="readonly" placeholder="Select date" title="Choose a date or range from the calendar" />
        </div>
    </td>
    <td>
        <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" title="Date this entry was posted in the system" />
    </td>
    @if($includeTransNo)
        <td>
            <input class="form-control unique_trans_no_invoice" type="text" value="" readonly/>
            <input class="unique_trans_no_hidden_invoice" name="trans_no[]" type="hidden" value="" />
        </td>
    @endif
    <td>
        <select class="form-control payment_type_invoice_per_row payment_type_cls" name="payment_type[]" data-valid="required">
            <option value="">Select</option>
            @foreach(\App\Support\InvoiceChargeTypes::options() as $chargeType)
                <option value="{{ $chargeType }}">{{ $chargeType }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <textarea data-valid="required" class="form-control invoice-line-description" name="description[]" rows="3"></textarea>
    </td>
    <td>
        <select class="form-control invoice-fee-earner" name="fee_earner_id[]">
            <option value="">Select</option>
            @foreach($feeEarners as $earner)
                <option value="{{ $earner->id }}">{{ trim(($earner->first_name ?? '').' '.($earner->last_name ?? '')) }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select class="form-control invoice-fee-earner-role" name="fee_earner_role[]">
            <option value="">Select</option>
            @foreach(\App\Support\InvoiceTimesheetLine::roles() as $role)
                <option value="{{ $role }}">{{ $role }}</option>
            @endforeach
        </select>
    </td>
    <td class="invoice-hours-col">
        <input class="form-control invoice-hours" name="hours[]" type="text" inputmode="decimal" placeholder="1.2" />
    </td>
    <td>
        <input class="form-control invoice-rate-ex-gst" name="rate_ex_gst[]" type="text" inputmode="decimal" placeholder="500.00" />
    </td>
    <td>
        <input data-valid="required" class="form-control invoice-amount-ex-gst" name="amount_ex_gst[]" type="text" inputmode="decimal" value="" />
    </td>
    <td>
        <input class="form-control invoice-line-gst" name="line_gst[]" type="text" inputmode="decimal" value="" />
    </td>
    <td>
        <a class="removeitems_invoice" href="javascript:;"><i class="fa-solid fa-xmark"></i></a>
    </td>
</tr>
