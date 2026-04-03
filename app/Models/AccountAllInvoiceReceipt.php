<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAllInvoiceReceipt extends Model
{
    protected $table = 'account_all_invoice_receipts';

    protected $fillable = [
        'user_id',
        'client_id',
        'client_matter_id',
        'receipt_id',
        'receipt_type',
        'trans_date',
        'entry_date',
        'trans_no',
        'description',
        'withdraw_amount',
        'withdraw_amount_before_void',
        'gst_included',
        'payment_type',
        'invoice_no',
        'save_type',
        'invoice_status',
    ];

    protected $casts = [
        // Removed all date and decimal casts to prevent parsing/casting errors
        // All formatting is handled robustly in the view layer with proper NULL handling
        // 'trans_date' => 'date',  // REMOVED: dates stored in d/m/Y format
        // 'entry_date' => 'date',  // REMOVED: dates stored in d/m/Y format
        // 'withdraw_amount' => 'decimal:2',  // REMOVED: causes cast errors on NULL
        // 'withdraw_amount_before_void' => 'decimal:2',  // REMOVED: causes cast errors on NULL
        // 'gst_included' => 'decimal:2',  // REMOVED: causes cast errors on NULL
    ];

    /**
     * Get the staff member who created this receipt
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * Get the client this receipt belongs to
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the client matter this receipt belongs to
     */
    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    /**
     * Get the account client receipt this is linked to
     */
    public function accountClientReceipt(): BelongsTo
    {
        return $this->belongsTo(AccountClientReceipt::class, 'receipt_id');
    }

    /**
     * Scope for filtering by receipt type
     */
    public function scopeByReceiptType($query, $type)
    {
        return $query->where('receipt_type', $type);
    }

    /**
     * Scope for filtering by client
     */
    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope for filtering by invoice number
     */
    public function scopeByInvoiceNo($query, $invoiceNo)
    {
        return $query->where('invoice_no', $invoiceNo);
    }

    /**
     * Scope for filtering by payment type
     */
    public function scopeByPaymentType($query, $paymentType)
    {
        return $query->where('payment_type', $paymentType);
    }
}
