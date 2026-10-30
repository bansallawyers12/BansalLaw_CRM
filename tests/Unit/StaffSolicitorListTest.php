<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Services\ClientEditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffSolicitorListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 16, 'name' => 'Solicitor', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function admin_flagged_as_legal_practitioner_appears_in_the_solicitor_list(): void
    {
        $michael = Staff::factory()->create([
            'status' => 1,
            'role' => 17,
            'first_name' => 'Michael',
            'last_name' => 'Saleh',
            'is_solicitor' => 1,
        ]);
        Staff::factory()->create([
            'status' => 1,
            'role' => 17,
            'first_name' => 'Office',
            'last_name' => 'Admin',
            'is_solicitor' => 0,
        ]);
        $solicitor = Staff::factory()->create([
            'status' => 1,
            'role' => 16,
            'first_name' => 'Ajay',
            'last_name' => 'Bansal',
            'is_solicitor' => 0,
        ]);

        $ids = ClientEditService::staffSelectableForSolicitorRole()->pluck('id')->all();

        $this->assertContains($michael->id, $ids);
        $this->assertContains($solicitor->id, $ids);
        $this->assertNotContains(
            Staff::query()->where('last_name', 'Admin')->value('id'),
            $ids
        );
    }
}
