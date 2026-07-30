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
    public function regular_staff_without_flag_cannot_edit_final_invoice(): void
    {
        $staff = new Staff(['role' => 16, 'status' => 1, 'can_edit_final_invoice' => false]);

        $this->assertFalse($staff->canEditFinalInvoice());
        $this->assertFalse(Staff::canGrantFinalInvoiceEditPermission($staff));
    }

    #[Test]
    public function regular_staff_with_flag_can_edit_final_invoice_but_cannot_grant(): void
    {
        $staff = new Staff(['role' => 16, 'status' => 1, 'can_edit_final_invoice' => true]);

        $this->assertTrue($staff->canEditFinalInvoice());
        $this->assertFalse(Staff::canGrantFinalInvoiceEditPermission($staff));
    }
}
