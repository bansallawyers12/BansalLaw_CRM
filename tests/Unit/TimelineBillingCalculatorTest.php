<?php

namespace Tests\Unit;

use App\Support\TimelineBillingCalculator;
use Tests\TestCase;

class TimelineBillingCalculatorTest extends TestCase
{
    public function test_matches_sample_statement_totals(): void
    {
        // Sample statement: 21 × $55.00 incl GST fees + InfoTrack disbursement
        $result = TimelineBillingCalculator::calculate(
            [['units' => 21]],
            [['net' => 67.89, 'gst' => 4.69]]
        );

        $this->assertSame(55.0, $result['unit_rate_incl_gst']);
        $this->assertSame(1155.0, $result['professional_fees']['incl_gst']);
        $this->assertSame(1050.0, $result['professional_fees']['net']);
        $this->assertSame(105.0, $result['professional_fees']['gst']);
        $this->assertSame(67.89, $result['disbursements']['net']);
        $this->assertSame(4.69, $result['disbursements']['gst']);
        $this->assertSame(72.58, $result['disbursements']['incl_gst']);
        $this->assertSame(1117.89, $result['total_fees_and_disbursements_net']);
        $this->assertSame(109.69, $result['gst_included']);
        $this->assertSame(1227.58, $result['total_amount_due']);
    }

    public function test_fee_line_amount_incl_gst_overrides_units(): void
    {
        $result = TimelineBillingCalculator::calculate([
            ['amount_incl_gst' => 110.0],
            ['units' => 1],
        ]);

        $this->assertSame(165.0, $result['professional_fees']['incl_gst']);
        $this->assertSame(15.0, $result['professional_fees']['gst']);
        $this->assertSame(150.0, $result['professional_fees']['net']);
    }

    public function test_disbursement_defaults_gst_from_net_when_omitted(): void
    {
        $normalized = TimelineBillingCalculator::normalizeDisbursement(['net' => 100.0]);

        $this->assertSame(100.0, $normalized['net']);
        $this->assertSame(10.0, $normalized['gst']);
        $this->assertSame(110.0, $normalized['incl_gst']);
    }
}
