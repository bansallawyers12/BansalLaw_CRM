<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Staff;
use App\Models\StaffLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Area7SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function regular_staff_cannot_delete_another_staff_members_action_task(): void
    {
        $staff1 = new Staff();
        $staff1->id = 101;
        $staff1->role = 2; // Regular Staff

        $this->actingAs($staff1, 'admin');

        $note = new Note();
        $note->id = 999;
        $note->user_id = 102; // Created by staff 102
        $note->assigned_to = 102; // Assigned to staff 102
        $note->is_action = 1;
        $note->save();

        $response = $this->delete('/assignee/999');

        $response->assertStatus(403);
    }

    #[Test]
    public function regular_staff_cannot_broadcast_to_all_staff(): void
    {
        $staff = new Staff();
        $staff->id = 103;
        $staff->role = 2; // Regular Staff

        $this->actingAs($staff, 'admin');

        $response = $this->postJson('/notifications/broadcasts/send', [
            'message' => 'Test broadcast',
            'scope' => 'all',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('Unauthorized access', $response->json('message'));
    }

    #[Test]
    public function regular_staff_cannot_view_audit_logs(): void
    {
        $staff = new Staff();
        $staff->id = 104;
        $staff->role = 2; // Regular Staff

        $this->actingAs($staff, 'admin');

        $response = $this->get('/audit-logs');

        $response->assertStatus(403);
    }

    #[Test]
    public function assignee_action_list_escapes_note_descriptions_preventing_xss(): void
    {
        $staff = new Staff();
        $staff->id = 105;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        $note = new Note();
        $note->id = 888;
        $note->user_id = 105;
        $note->assigned_to = 105;
        $note->type = 'client';
        $note->is_action = 1;
        $note->status = 0;
        $note->description = '<script>alert("XSS")</script>';
        $note->save();

        $response = $this->getJson('/tasks/list');

        $response->assertStatus(200);
        $content = json_encode($response->json());
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    #[Test]
    public function regular_staff_cannot_update_unauthorized_office_visit_checkin_status(): void
    {
        $staff = new Staff();
        $staff->id = 701;
        $staff->role = 2; // Regular staff

        $this->actingAs($staff, 'admin');

        $checkin = new \App\Models\CheckinLog();
        $checkin->id = 8888;
        $checkin->client_id = 901;
        $checkin->user_id = 702; // Assigned to another staff
        $checkin->office = 1;
        $checkin->visit_purpose = 'Consultation';
        $checkin->contact_type = 'Client';
        $checkin->status = 0;
        $checkin->date = date('Y-m-d');
        $checkin->save();

        $response = $this->postJson('/dashboard/update-checkin-status', [
            'checkin_id' => 8888,
            'status' => 1,
        ]);

        $response->assertStatus(403);
        $this->assertFalse($response->json('success'));
        $this->assertEquals('Unauthorized access to check-in record', $response->json('message'));
    }

    #[Test]
    public function office_visit_getcheckin_returns_404_when_record_missing(): void
    {
        $staff = new Staff();
        $staff->id = 705;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        $response = $this->getJson('/get-checkin-detail?id=9999999');

        $response->assertStatus(404);
        $this->assertFalse($response->json('status'));
        $this->assertEquals('Checkin log record not found', $response->json('message'));
    }

    #[Test]
    public function public_wallet_payment_verifies_stripe_payment_intent(): void
    {
        $appointment = new \App\Models\BookingAppointment();
        $appointment->id = 77777;
        $appointment->bansal_appointment_id = 77777;
        $appointment->client_name = 'Test Client';
        $appointment->client_email = 'testclient@example.com';
        $appointment->appointment_datetime = now()->addDays(2);
        $appointment->location = 'melbourne';
        $appointment->service_id = 1;
        $appointment->is_paid = false;
        $appointment->payment_status = 'pending';
        $appointment->amount = 150.00;
        $appointment->save();

        // Attempting to record payment with an unconfirmed/fake payment intent ID fails Stripe verification
        $response = $this->postJson('/api/appointments/record-payment-without-login-wallet', [
            'appointment_id' => 77777,
            'payment_intent_id' => 'pi_fake_unverified_intent',
            'payment_type' => 'gpay',
        ]);

        // Expect 422 Unprocessable Entity because Stripe API fails to retrieve unverified intent
        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }
}
