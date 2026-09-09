<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonalCalendarAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'Solicitor', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createStaff(array $attrs = []): Staff
    {
        return Staff::create(array_merge([
            'first_name' => 'Sam',
            'last_name' => 'Staff',
            'email' => 'sam.calendar.' . uniqid('', true) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 16,
            'status' => 1,
            'can_access_personal_calendar' => false,
        ], $attrs));
    }

    #[Test]
    public function staff_without_calendar_permission_cannot_load_calendar_events(): void
    {
        $staff = $this->createStaff(['can_access_personal_calendar' => false]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events'))
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    #[Test]
    public function staff_with_calendar_permission_can_load_calendar_events(): void
    {
        $staff = $this->createStaff(['can_access_personal_calendar' => true]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    #[Test]
    public function super_admin_can_load_calendar_events_without_flag(): void
    {
        $staff = $this->createStaff([
            'role' => 1,
            'can_access_personal_calendar' => false,
            'first_name' => 'Super',
            'email' => 'super.calendar@example.com',
        ]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    #[Test]
    public function staff_without_calendar_permission_cannot_create_calendar_event(): void
    {
        $staff = $this->createStaff(['can_access_personal_calendar' => false]);
        $this->actingAs($staff, 'admin');

        $this->postJson(route('booking.api.calendar-events.store'), [
            'title' => 'Blocked reminder',
            'event_type' => 'reminder',
            'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'ends_at' => now()->addDay()->setTime(10, 30)->toIso8601String(),
        ])
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    #[Test]
    public function non_super_admin_cannot_force_all_staff_calendar_view(): void
    {
        $staff = $this->createStaff(['can_access_personal_calendar' => true]);
        $this->actingAs($staff, 'admin');

        $response = $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
            'staff_view' => 'all',
        ]));

        $response->assertOk()->assertJson([
            'success' => true,
            'staff_view' => 'self',
        ]);
    }

    #[Test]
    public function super_admin_defaults_to_all_staff_calendar_view(): void
    {
        $staff = $this->createStaff([
            'role' => 1,
            'first_name' => 'Super',
            'email' => 'super.default.calendar@example.com',
        ]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'staff_view' => 'all',
            ]);
    }

    #[Test]
    public function super_admin_can_request_all_staff_calendar_view(): void
    {
        $staff = $this->createStaff([
            'role' => 1,
            'can_access_personal_calendar' => false,
            'first_name' => 'Super',
            'email' => 'super.all.calendar@example.com',
        ]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
            'staff_view' => 'all',
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'staff_view' => 'all',
            ]);
    }

    #[Test]
    public function super_admin_can_request_individual_staff_calendar_view(): void
    {
        $super = $this->createStaff([
            'role' => 1,
            'first_name' => 'Super',
            'email' => 'super.pick.calendar@example.com',
        ]);
        $other = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'Other',
            'email' => 'other.pick.calendar@example.com',
        ]);
        $this->actingAs($super, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
            'staff_view' => $other->id,
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'staff_view' => 'staff',
            ]);
    }

    #[Test]
    public function super_admin_individual_staff_list_only_includes_calendar_access(): void
    {
        $super = $this->createStaff([
            'role' => 1,
            'first_name' => 'Super',
            'email' => 'super.list.calendar@example.com',
        ]);
        $withAccess = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'WithAccess',
            'email' => 'with.access.calendar@example.com',
        ]);
        $withoutAccess = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => false,
            'first_name' => 'NoAccess',
            'email' => 'no.access.calendar@example.com',
        ]);
        $this->actingAs($super, 'admin');

        $options = app(\App\Services\StaffPersonalCalendarFeedService::class)->staffFilterOptions();
        $ids = collect($options)->pluck('id')->all();

        $this->assertContains($super->id, $ids);
        $this->assertContains($withAccess->id, $ids);
        $this->assertNotContains($withoutAccess->id, $ids);
    }
}
