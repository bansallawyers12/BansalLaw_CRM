<?php

namespace Tests\Unit;

use App\Support\InvoiceChargeTypes;
use PHPUnit\Framework\TestCase;

class InvoiceChargeTypesTest extends TestCase
{
    public function test_options_include_requested_charge_types(): void
    {
        $options = InvoiceChargeTypes::options();

        $this->assertContains('Professional Fees', $options);
        $this->assertContains('Barrister Fees', $options);
        $this->assertContains('Court Fees', $options);
        $this->assertContains('VOI Charges', $options);
        $this->assertContains('Disbursements', $options);
        $this->assertContains('Discount', $options);
        $this->assertNotContains('Professional Fee', $options);
        $this->assertNotContains('Department Charges', $options);
    }

    public function test_normalize_maps_legacy_payment_types(): void
    {
        $this->assertSame('Professional Fees', InvoiceChargeTypes::normalize('Professional Fee'));
        $this->assertSame('Government Fees', InvoiceChargeTypes::normalize('Department Charges'));
        $this->assertSame('Other Costs', InvoiceChargeTypes::normalize('Other Cost'));
        $this->assertSame('Disbursements', InvoiceChargeTypes::normalize('Disbursement'));
        $this->assertSame('Barrister Fees', InvoiceChargeTypes::normalize('Barrister Fees'));
    }

    public function test_groups_include_legacy_aliases(): void
    {
        $groups = InvoiceChargeTypes::groups();

        $this->assertContains('Professional Fee', $groups['professional_fees']['types']);
        $this->assertContains('Department Charges', $groups['government_fees']['types']);
        $this->assertContains('Other Cost', $groups['other_costs']['types']);
        $this->assertSame('VOI Charges', $groups['voi_charges']['title']);
    }
}
