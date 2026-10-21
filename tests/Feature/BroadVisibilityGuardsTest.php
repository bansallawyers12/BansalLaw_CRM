<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BookingAppointment;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Staff;
use App\Services\StaffPersonalCalendarFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadVisibilityGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Staff', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'name' => 'Calling Team', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'Solicitor', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function staff(array $attrs = []): Staff
    {
        return Staff::factory()->create(array_merge([
            'status' => 1,
            'role' => 2,
            'first_name' => 'Priya',
            'last_name' => 'Sharma',
            'email' => 'priya.' . uniqid('', true) . '@example.test',
        ], $attrs));
    }

    private function broadcastBatch(int $senderId, int $receiverId, string $uuid): void
    {
        DB::table('notifications')->insert([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'url' => '/broadcasts/' . $uuid,
            'notification_type' => 'broadcast',
            'message' => "Title\nSecret broadcast body",
            'sender_status' => 1,
            'receiver_status' => 0,
            'seen' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function assigned_staff_get_client_appointments_and_unrelated_staff_get_403(): void
    {
        $assigned = $this->staff(['email' => 'assigned.appointments@example.test']);
        $unrelated = $this->staff(['first_name' => 'Rohan', 'email' => 'unrelated.appointments@example.test']);

        $client = Admin::factory()->create(['type' => 'client']);
        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'APPT-1',
            'sel_legal_practitioner' => $assigned->id,
        ]);
        BookingAppointment::create([
            'bansal_appointment_id' => random_int(100000, 999999),
            'client_name' => 'Appointment Client',
            'client_email' => 'appointment.client@example.test',
            'client_phone' => '0400000000',
            'location' => 'melbourne',
            'timeslot_full' => '10:00 AM - 10:30 AM',
            'client_id' => $client->id,
            'user_id' => $assigned->id,
            'service_type' => 'Visa Consultation Secret',
            'enquiry_details' => 'Appointment detail',
            'appointment_datetime' => now()->addDay(),
            'status' => 'pending',
            'is_paid' => 1,
        ]);

        $this->actingAs($assigned, 'admin');
        $this->get('/get-appointments?clientid=' . $client->id)
            ->assertOk()
            ->assertSee('Visa Consultation Secret', false);

        $this->actingAs($unrelated, 'admin');
        $this->getJson('/get-appointments?clientid=' . $client->id)
            ->assertStatus(403)
            ->assertDontSee('Visa Consultation Secret', false);

        $this->getJson('/get-appointments')
            ->assertStatus(400);
        $this->getJson('/get-appointments?clientid=abc')
            ->assertStatus(400);
    }

    #[Test]
    public function reopen_only_reopens_group_notes_the_actor_may_manage(): void
    {
        $creator = $this->staff(['email' => 'creator.reopen@example.test']);
        $assignee = $this->staff(['email' => 'assignee.reopen@example.test']);
        $otherAssignee = $this->staff(['email' => 'other.reopen@example.test']);
        $unrelated = $this->staff(['first_name' => 'Rohan', 'email' => 'unrelated.reopen@example.test']);

        $mine = Note::create([
            'description' => 'My completed task',
            'user_id' => $creator->id,
            'assigned_to' => $assignee->id,
            'status' => 1,
            'is_action' => 1,
            'unique_group_id' => 'group-reopen-1',
        ]);
        $theirs = Note::create([
            'description' => 'Their completed task',
            'user_id' => $creator->id,
            'assigned_to' => $otherAssignee->id,
            'status' => 1,
            'is_action' => 1,
            'unique_group_id' => 'group-reopen-1',
        ]);

        $this->actingAs($unrelated, 'admin');
        $this->post('/tasks/reopen', ['id' => $mine->id])
            ->assertStatus(403);
        $this->assertSame(1, (int) $mine->fresh()->status);

        $this->actingAs($assignee, 'admin');
        $this->post('/tasks/reopen', ['id' => $mine->id, 'unique_group_id' => 'group-reopen-1'])
            ->assertOk()
            ->assertJson(['status' => true]);
        $this->assertSame(0, (int) $mine->fresh()->status);
        $this->assertSame(1, (int) $theirs->fresh()->status);
    }

    #[Test]
    public function signature_pages_do_not_embed_other_clients_leads_or_staff_emails_for_unrelated_user(): void
    {
        $viewer = $this->staff(['email' => 'viewer.signature@example.test']);
        $otherStaff = $this->staff(['first_name' => 'Rohan', 'email' => 'other.staff.signature@example.test']);
        Admin::factory()->create([
            'type' => 'client',
            'email' => 'secret.client.signature@example.test',
            'user_id' => $otherStaff->id,
        ]);
        Admin::factory()->create([
            'type' => 'lead',
            'email' => 'secret.lead.signature@example.test',
            'user_id' => $otherStaff->id,
        ]);

        $this->actingAs($viewer, 'admin');

        $this->get(route('signatures.create'))
            ->assertOk()
            ->assertDontSee('secret.client.signature@example.test', false)
            ->assertDontSee('secret.lead.signature@example.test', false);

        $index = $this->get(route('signatures.index'))
            ->assertOk()
            ->assertDontSee('secret.lead.signature@example.test', false);

        // The attach-modal staff payload is the `clients` JSON embedded by the
        // dashboard; other staff emails must not appear in that payload.
        $this->assertMatchesRegularExpression('/const clients = (\[.*?\]);/s', $index->getContent());
        preg_match('/const clients = (\[.*?\]);/s', $index->getContent(), $matches);
        $this->assertStringNotContainsString('other.staff.signature@example.test', $matches[1]);
        $this->assertStringContainsString('viewer.signature@example.test', $matches[1]);
    }

    #[Test]
    public function broadcast_history_details_and_delete_are_scoped_to_sender_unless_broad_visibility(): void
    {
        $sender = $this->staff(['email' => 'sender.broadcast@example.test']);
        $viewer = $this->staff(['email' => 'viewer.broadcast@example.test']);
        $broad = $this->staff(['role' => 14, 'email' => 'broad.broadcast@example.test']);
        $uuid = '123e4567-e89b-42d3-a456-426614174000';
        $this->broadcastBatch($sender->id, $viewer->id, $uuid);

        $this->actingAs($viewer, 'admin');
        $history = $this->getJson(route('notifications.broadcasts.history'))->assertOk();
        $this->assertStringNotContainsString($uuid, $history->getContent());

        $this->getJson(route('notifications.broadcasts.details', ['batchUuid' => $uuid]))
            ->assertStatus(404);

        $this->deleteJson(route('notifications.broadcasts.delete', ['batchUuid' => $uuid]))
            ->assertStatus(403);
        $this->assertTrue(DB::table('notifications')->where('url', '/broadcasts/' . $uuid)->exists());

        $this->actingAs($broad, 'admin');
        $history = $this->getJson(route('notifications.broadcasts.history'))->assertOk();
        $this->assertStringContainsString($uuid, $history->getContent());

        $this->getJson(route('notifications.broadcasts.details', ['batchUuid' => $uuid]))
            ->assertOk();

        $this->deleteJson(route('notifications.broadcasts.delete', ['batchUuid' => $uuid]))
            ->assertOk();
        $this->assertFalse(DB::table('notifications')->where('url', '/broadcasts/' . $uuid)->exists());
    }

    #[Test]
    public function active_staff_endpoint_is_broad_visibility_only(): void
    {
        $viewer = $this->staff(['email' => 'viewer.active@example.test']);
        $broad = $this->staff(['role' => 14, 'email' => 'broad.active@example.test']);

        $this->actingAs($viewer, 'admin');
        $this->getJson(route('dashboard.active-staff'))->assertStatus(403);

        $this->actingAs($broad, 'admin');
        $this->getJson(route('dashboard.active-staff'))->assertOk();
    }

    #[Test]
    public function closed_matters_list_is_broad_visibility_only(): void
    {
        $viewer = $this->staff(['email' => 'viewer.closed@example.test']);
        $broad = $this->staff(['role' => 14, 'email' => 'broad.closed@example.test']);

        $this->actingAs($viewer, 'admin');
        $this->get(route('clients.closedmatterslist'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($broad, 'admin');
        $this->get(route('clients.closedmatterslist'))->assertOk();
    }

    #[Test]
    public function other_calendar_menu_and_booking_calendar_feeds_are_denied_for_normal_users(): void
    {
        $viewer = $this->staff(['email' => 'viewer.calendar@example.test', 'can_access_personal_calendar' => true]);
        $ajay = $this->staff(['first_name' => 'Ajay', 'last_name' => 'Consultant', 'email' => 'ajay.consultant@example.test', 'can_access_personal_calendar' => true]);

        $this->actingAs($viewer, 'admin');
        $this->assertSame([], app(StaffPersonalCalendarFeedService::class)->staffFilterOptions($viewer));

        $this->getJson(route('booking.api.appointments', ['format' => 'calendar', 'type' => 'ajay']))
            ->assertStatus(403);
        $this->get(route('booking.appointments.calendar', ['type' => 'ajay']))
            ->assertStatus(403);

        $this->actingAs($ajay, 'admin');
        $this->getJson(route('booking.api.appointments', ['format' => 'calendar', 'type' => 'ajay', 'start' => now()->toIso8601String(), 'end' => now()->addMonth()->toIso8601String()]))
            ->assertOk();
    }

    #[Test]
    public function dashboard_calendar_feed_ignores_another_consultants_calendar_type(): void
    {
        $viewer = $this->staff([
            'email' => 'viewer.dashcal@example.test',
            'can_access_personal_calendar' => true,
        ]);
        $super = $this->staff([
            'role' => 1,
            'first_name' => 'Super',
            'email' => 'super.dashcal@example.test',
            'can_access_personal_calendar' => true,
        ]);
        $range = [
            'start' => now()->toIso8601String(),
            'end' => now()->addMonth()->toIso8601String(),
            'booking_calendar_type' => 'kunal',
        ];

        $this->actingAs($viewer, 'admin');
        $this->getJson(route('dashboard.calendar-events', $range))
            ->assertOk()
            ->assertJsonPath('booking_calendar_type', null);

        $this->actingAs($super, 'admin');
        $this->getJson(route('dashboard.calendar-events', $range))
            ->assertOk()
            ->assertJsonPath('booking_calendar_type', 'kunal');
    }
}
