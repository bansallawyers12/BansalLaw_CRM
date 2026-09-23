<div class="invoice-billing-mode-bar">
    <div class="invoice-billing-mode-bar__main">
        <span class="invoice-billing-mode-label"><i class="fa-solid fa-calculator me-1 text-primary"></i> Billing method</span>
        <div class="btn-group btn-group-sm invoice-billing-mode-toggle" role="group" aria-label="Invoice billing type">
            <button type="button" class="btn btn-primary invoice-mode-btn" data-invoice-mode="hourly"><i class="fa-regular fa-clock me-1"></i> Hourly</button>
            <button type="button" class="btn btn-outline-secondary invoice-mode-btn" data-invoice-mode="fixed"><i class="fa-solid fa-receipt me-1"></i> Fixed fee</button>
        </div>
    </div>
    <p class="invoice-billing-mode-hint mb-0">
        <i class="fa-solid fa-circle-info text-info me-1"></i>
        <span class="invoice-billing-hint-hourly">Enter hours and rate — amount and GST calculate automatically.</span>
        <span class="invoice-billing-hint-fixed" hidden>Enter the fee amount — GST is calculated automatically.</span>
    </p>
    <input type="hidden" name="invoice_billing_mode" class="invoice-billing-mode" value="hourly" />
</div>
