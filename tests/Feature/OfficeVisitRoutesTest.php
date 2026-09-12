<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfficeVisitRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function office_visit_route_names_use_hyphenated_prefix(): void
    {
        $this->assertSame(url('/office-visits'), route('office-visits.index'));
        $this->assertSame(url('/office-visits/waiting'), route('office-visits.waiting'));
        $this->assertSame(url('/office-visits/attending'), route('office-visits.attending'));
        $this->assertSame(url('/office-visits/completed'), route('office-visits.completed'));
        $this->assertSame(url('/office-visits/create'), route('office-visits.create'));
    }

    #[Test]
    public function legacy_office_visits_create_redirects_to_front_desk_checkin(): void
    {
        $staff = new Staff();
        $staff->id = 920;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        $this->get('/office-visits/create')
            ->assertRedirect('/front-desk/checkin')
            ->assertStatus(301);
    }
}
