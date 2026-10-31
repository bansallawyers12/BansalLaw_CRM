<?php

namespace Tests\Unit;

use App\Models\Staff;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffEditFinalInvoicePermissionTest extends TestCase
{
    #[Test]
    public function super_admin_can_edit_final_invoice_without_flag(): void
    {
        $staff = new Staff(['role' => 1, 'status' => 1, 'can_edit_final_invoice' => false]);

        $this->assertTrue($staff->canEditFinalInvoice());
        $this->assertTrue(Staff::canGrantFinalInvoiceEditPermission($staff));
    }

    #[Test]
    public function admin_can_edit_final_invoice_without_flag(): void
    {
        $staff = new Staff(['role' => 17, 'status' => 1, 'can_edit_final_invoice' => false]);

        $this->assertTrue($staff->canEditFinalInvoice());
        $this->assertTrue(Staff::canGrantFinalInvoiceEditPermission($staff));
    }

    #[Test]
    public function any_active_staff_can_edit_an_unpaid_final_invoice_on_a_matter_they_can_open(): void
    {
        $staff = new Staff(['role' => 16, 'status' => 1, 'can_edit_final_invoice' => false]);

        $this->assertTrue($staff->canEditFinalInvoice());
        $this->assertFalse(Staff::canGrantFinalInvoiceEditPermission($staff));
    }

    #[Test]
    public function admin_access_can_edit_final_invoice_without_a_separate_flag(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'grant_super_admin_access' => true,
            'can_edit_final_invoice' => false,
        ]);

        $this->assertTrue($staff->canEditFinalInvoice());
        $this->assertFalse(Staff::canGrantFinalInvoiceEditPermission($staff));
    }
}
