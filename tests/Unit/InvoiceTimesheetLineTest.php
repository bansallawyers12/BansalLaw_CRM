<?php

namespace Tests\Unit;

use App\Support\InvoiceTimesheetLine;
use PHPUnit\Framework\TestCase;

class InvoiceTimesheetLineTest extends TestCase
{
    public function test_hourly_line_multiplies_hours_by_rate_and_adds_gst(): void
    {
        $line = InvoiceTimesheetLine::fromRequest([
            'billing_basis' => ['hourly'],
            'hours' => ['1.2'],
            'rate_ex_gst' => ['500.00'],
            'fee_earner_role' => ['Solicitor'],
            'payment_type' => ['Professional Fees'],
        ], 0);

        $this->assertSame(600.0, $line['amount_ex_gst']);
        $this->assertSame(60.0, $line['line_gst']);
        $this->assertSame(660.0, $line['withdraw_amount']);
        $this->assertSame('Yes', $line['gst_included']);
        $this->assertSame('Solicitor', $line['fee_earner_role']);
    }

    public function test_fixed_line_uses_entered_amount_and_gst_column(): void
    {
        $line = InvoiceTimesheetLine::fromRequest([
            'billing_basis' => ['fixed'],
            'amount_ex_gst' => ['1500.00'],
            'line_gst' => ['150.00'],
            'fee_earner_role' => ['Solicitor'],
            'payment_type' => ['Professional Fees'],
        ], 0);

        $this->assertSame(1500.0, $line['amount_ex_gst']);
        $this->assertSame(150.0, $line['line_gst']);
        $this->assertSame(1650.0, $line['withdraw_amount']);
        $this->assertSame('fixed', $line['billing_basis']);
    }

    public function test_legacy_gst_inclusive_amount_is_split(): void
    {
        $line = InvoiceTimesheetLine::fromRequest([
            'gst_included' => ['Yes'],
            'withdraw_amount' => ['550.00'],
            'payment_type' => ['Professional Fees'],
        ], 0);

        $this->assertSame(500.0, $line['amount_ex_gst']);
        $this->assertSame(50.0, $line['line_gst']);
        $this->assertSame(550.0, $line['withdraw_amount']);
    }

    public function test_pdf_totals_sum_gst_column(): void
    {
        $lines = [
            (object) [
                'payment_type' => 'Professional Fees',
                'amount_ex_gst' => 5400,
                'line_gst' => 540,
                'withdraw_amount' => 5940,
            ],
        ];

        $totals = InvoiceTimesheetLine::pdfTotals($lines);

        $this->assertSame(5400.0, $totals['ex_gst']);
        $this->assertSame(540.0, $totals['gst']);
        $this->assertSame(5940.0, $totals['incl_gst']);
    }
}
