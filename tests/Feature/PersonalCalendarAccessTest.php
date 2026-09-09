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
}
