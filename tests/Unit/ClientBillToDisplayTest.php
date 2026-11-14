<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\Company;
use App\Support\ClientBillToDisplay;
use Tests\TestCase;

class ClientBillToDisplayTest extends TestCase
{
    public function test_company_client_uses_company_name_not_contact_person_on_admin(): void
    {
        $admin = Admin::factory()->create([
            'is_company' => 1,
            'first_name' => 'Ankit',
            'last_name' => 'Bansal',
            'client_id' => 'BANS2600060',
        ]);

        Company::query()->create([
            'admin_id' => $admin->id,
            'company_name' => 'Acme Pty Ltd',
        ]);

        $admin->load('company');

        $this->assertSame('Acme Pty Ltd', ClientBillToDisplay::billingName($admin));
    }

    public function test_personal_client_uses_full_name(): void
    {
        $admin = Admin::factory()->create([
            'is_company' => 0,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'client_id' => 'JANE2600001',
        ]);

        $this->assertSame('Jane Doe', ClientBillToDisplay::billingName($admin));
    }
}