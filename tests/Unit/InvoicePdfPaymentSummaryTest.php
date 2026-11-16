<?php

namespace Tests\Unit;

use App\Support\InvoicePdfPaymentSummary;
use PHPUnit\Framework\TestCase;

class InvoicePdfPaymentSummaryTest extends TestCase
{
    public function test_auto_shows_paid_footer_when_invoice_is_paid(): void
    {
        $receipt = (object) [
            'invoice_status' => 1,
            'balance_amount' => 0,
            'partial_paid_amount' => 1500.50,
        ];

        $summary = InvoicePdfPaymentSummary::footerRows($receipt, 1500.50, 0, 'auto');

        $this->assertSame('Amount is Paid:', $summary['rows'][0]['label']);
        $this->assertEquals(1500.50, $summary['rows'][0]['amount']);
        $this->assertFalse($summary['show_payment_instructions']);
    }

    public function test_auto_shows_partial_paid_and_pending_rows(): void
    {
        $receipt = (object) [
            'invoice_status' => 2,
            'balance_amount' => 500,
            'partial_paid_amount' => 1000,
        ];

        $summary = InvoicePdfPaymentSummary::footerRows($receipt, 1500, 500, 'auto');

        $this->assertCount(2, $summary['rows']);
        $this->assertSame('Amount is Paid:', $summary['rows'][0]['label']);
        $this->assertSame('Amount is Pending:', $summary['rows'][1]['label']);
        $this->assertTrue($summary['show_payment_instructions']);
    }

    public function test_paid_mode_forces_paid_footer(): void
    {
        $receipt = (object) [
            'invoice_status' => 0,
            'balance_amount' => 2000,
            'partial_paid_amount' => 0,
        ];

        $summary = InvoicePdfPaymentSummary::footerRows($receipt, 2000, 2000, 'paid');

        $this->assertSame('Amount is Paid:', $summary['rows'][0]['label']);
        $this->assertEquals(2000, $summary['rows'][0]['amount']);
        $this->assertFalse($summary['show_payment_instructions']);
    }

    public function test_pending_mode_keeps_pending_label(): void
    {
        $receipt = (object) [
            'invoice_status' => 1,
            'balance_amount' => 0,
            'partial_paid_amount' => 800,
        ];

        $summary = InvoicePdfPaymentSummary::footerRows($receipt, 800, 0, 'pending');

        $this->assertSame('Amount is Pending:', $summary['rows'][0]['label']);
        $this->assertTrue($summary['show_payment_instructions']);
    }
}