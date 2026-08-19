<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TrustAccounting\TrustLedgerBalanceService;
use App\Services\TrustAccounting\TrustReportQueryService;
use App\Services\TrustAccounting\TrustPeriodService;
use App\Http\Controllers\API\LeadBookingApiController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SecurityBugFixes14Test extends TestCase
{
    use DatabaseTransactions;
    /** @test */
    public function test_14_1_trust_reversal_entry_excluded_from_balances()
    {
        $reversalRow = (object) [
            'trust_voided_at' => null,
            'trust_reversal_of_entry_id' => 123,
            'void_fee_transfer' => 0,
        ];

        $this->assertTrue(TrustLedgerBalanceService::rowExcludedFromBalance($reversalRow));
    }

    /** @test */
    public function test_14_8_period_lock_handles_hyphenated_and_custom_dates()
    {
        // 1. Should parse both hyphenated (YYYY-MM-DD) and slash (DD/MM/YYYY) date strings cleanly
        try {
            TrustPeriodService::assertTransDateUnlocked('2026-07-31');
            TrustPeriodService::assertTransDateUnlocked('31/07/2026');
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Invalid transaction date format')) {
                $this->fail('Failed on valid date string format: ' . $e->getMessage());
            }
        }

        // 2. Should throw RuntimeException with invalid format when given arbitrary bad dates
        $badDateExceptionThrown = false;
        try {
            TrustPeriodService::assertTransDateUnlocked('invalid-date-xyz');
        } catch (\RuntimeException $e) {
            $badDateExceptionThrown = true;
            $this->assertStringContainsString('Invalid transaction date format', $e->getMessage());
        }
        $this->assertTrue($badDateExceptionThrown, 'Should throw RuntimeException for invalid date');

        // 3. Test locking behavior with an actual locked accounting period in DB
        \Illuminate\Support\Facades\DB::table('trust_accounting_periods')->insert([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => 'locked',
            'locked_at' => now(),
            'notes' => 'July 2026 Audit Period',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A date inside the locked period (tested in both formats) should be blocked
        $this->assertTrue(TrustPeriodService::isLockedForRow('15/07/2026'));
        $this->assertTrue(TrustPeriodService::isLockedForRow('2026-07-15'));

        $lockedExceptionThrown = false;
        try {
            TrustPeriodService::assertTransDateUnlocked('15/07/2026');
        } catch (\RuntimeException $e) {
            $lockedExceptionThrown = true;
            $this->assertStringContainsString('locked trust accounting period', $e->getMessage());
        }
        $this->assertTrue($lockedExceptionThrown, 'Locked period must throw lock exception for date in period');

        // A date outside the locked period should not be blocked
        $this->assertFalse(TrustPeriodService::isLockedForRow('01/08/2026'));
        $this->assertFalse(TrustPeriodService::isLockedForRow('2026-08-01'));
    }

    /** @test */
    public function test_14_9_public_booking_api_forces_unpaid_for_unauthenticated_requests()
    {
        // Ensure no admin is logged in
        \Illuminate\Support\Facades\Auth::guard('admin')->logout();

        $controller = new LeadBookingApiController();
        $request = new Request([
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'appointment_datetime' => '2026-08-01 10:00:00',
            'location' => 'melbourne',
            'enquiry_details' => 'General legal enquiry',
            'is_paid' => true,
            'payment_status' => 'completed',
            'status' => 'paid',
        ]);

        $response = $controller->storeBookingAppointment($request);
        $this->assertNotNull($response);
        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $appointmentId = $responseData['data']['id'];

        // Verify that in the database, unauthenticated caller was forced to unpaid/pending
        $appointment = \App\Models\BookingAppointment::find($appointmentId);
        $this->assertFalse((bool) $appointment->is_paid, 'is_paid must be forced to false for unauthenticated API requests');
        $this->assertEquals('pending', $appointment->payment_status, 'payment_status must be forced to pending for unauthenticated API requests');
        $this->assertNull($appointment->paid_at, 'paid_at must be null for unauthenticated API requests');
        $this->assertEquals('pending', $appointment->status, 'status=paid must be demoted to pending for unauthenticated API requests');
    }

    /** @test */
    public function test_14_10_paid_booking_created_with_is_paid_false_until_payment_completed()
    {
        $client = \App\Models\Admin::create([
            'first_name' => 'PaidBooking',
            'last_name' => 'Tester',
            'email' => 'paidbooking_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $mockClient = $this->createMock(\App\Services\BansalAppointmentSync\BansalApiClient::class);
        $mockClient->method('createAppointment')->willReturn([
            'data' => ['id' => 9990000 + mt_rand(1, 99999)],
        ]);
        $this->app->instance(\App\Services\BansalAppointmentSync\BansalApiClient::class, $mockClient);

        $publicBookingController = new \App\Http\Controllers\API\PublicBookingController();
        $randomDay = mt_rand(10, 28);
        $request = new Request([
            'full_name' => 'PaidBooking Tester',
            'email' => $client->email,
            'phone' => '0412345678',
            'service_id' => 2, // Form service_id 2 = Standard Paid Consultation ($150, 30 min)
            'noe_id' => 1,
            'appoint_date' => "{$randomDay}/12/2026",
            'appoint_time' => '10:00 AM-10:30 AM',
            'location' => 'melbourne',
            'inperson_address' => 2,
            'appointment_details' => 2, // in_person
            'preferred_language' => 1, // English
            'description' => 'Legal matter needing consultation',
            'timezone' => 'Australia/Melbourne',
        ]);

        $response = $publicBookingController->addAppointmentWithoutLogin($request);
        $this->assertNotNull($response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success'] ?? false, 'Response was not success: ' . $response->getContent());
        $appointmentId = $responseData['data']['id'];

        $appointment = \App\Models\BookingAppointment::find($appointmentId);
        // Prior to payment completion, paid service must NOT be marked is_paid = true
        $this->assertFalse((bool) $appointment->is_paid, 'Paid service should have is_paid = false prior to checkout payment');
        $this->assertEquals('pending', $appointment->payment_status, 'Payment status should be pending prior to checkout payment');
        $this->assertEquals('pending', $appointment->status, 'Status should be pending prior to checkout payment');
    }

    /** @test */
    public function test_14_11_record_payment_by_intent_enforces_strong_ownership()
    {
        $client = \App\Models\Admin::create([
            'first_name' => 'Intent',
            'last_name' => 'Owner',
            'email' => 'owner_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $appointment = \App\Models\BookingAppointment::create([
            'bansal_appointment_id' => 9990000 + mt_rand(1, 99999),
            'client_id' => $client->id,
            'client_name' => 'Intent Owner',
            'client_email' => $client->email,
            'appointment_datetime' => '2026-09-03 10:00:00',
            'location' => 'melbourne',
            'amount' => 150.00,
            'final_amount' => 150.00,
            'service_id' => 1,
            'noe_id' => 1,
            'status' => 'pending',
            'payment_status' => 'pending',
            'is_paid' => false,
        ]);

        $stripeService = new \App\Services\Payment\StripePaymentService();

        // Non-existent or mismatched PaymentIntent ID is rejected safely
        $result = $stripeService->recordPaymentByIntent($appointment, 'pi_nonexistent_123');
        $this->assertFalse($result['success']);
    }

    /** @test */
    public function test_14_12_logged_in_booking_rejects_when_bansal_slot_unavailable()
    {
        $client = \App\Models\Admin::create([
            'first_name' => 'Slot',
            'last_name' => 'Tester',
            'email' => 'slot_tester_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        \Illuminate\Support\Facades\Auth::login($client);

        // Mock BansalApiClient to throw slot unavailable exception
        $mockClient = $this->createMock(\App\Services\BansalAppointmentSync\BansalApiClient::class);
        $mockClient->method('createAppointment')->willThrowException(new \Exception('Selected time slot is already booked'));
        $this->app->instance(\App\Services\BansalAppointmentSync\BansalApiClient::class, $mockClient);

        $clientsController = app(\App\Http\Controllers\CRM\ClientsController::class);
        $request = new Request([
            'client_id' => $client->id,
            'service_id' => 'paid', // Standard Paid Consultation slug
            'noe_id' => 1,
            'appoint_date' => '15/09/2026',
            'appoint_time' => '11:00 AM-11:30 AM',
            'location' => 'melbourne',
            'inperson_address' => 2,
            'appointment_details' => 'in_person',
            'preferred_language' => 'English',
            'description' => 'Slot conflict check',
            'timezone' => 'Australia/Melbourne',
        ]);

        $response = $clientsController->addAppointmentBook($request);
        $this->assertNotNull($response);
        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['status']);
        $this->assertStringContainsString('time slot is not available', $responseData['message']);

        // Verify that NO local appointment was created in the database
        $created = \App\Models\BookingAppointment::where('client_id', $client->id)
            ->where('enquiry_details', 'Slot conflict check')
            ->first();
        $this->assertNull($created, 'Local appointment should NOT be created when Bansal slot is unavailable');
    }

    /** @test */
    public function test_14_13_duplicate_slot_check_blocks_across_different_clients()
    {
        $clientA = \App\Models\Admin::create([
            'first_name' => 'ClientA',
            'last_name' => 'Tester',
            'email' => 'client_a_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $clientB = \App\Models\Admin::create([
            'first_name' => 'ClientB',
            'last_name' => 'Tester',
            'email' => 'client_b_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $appointmentDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', '2026-10-10 14:00', 'Australia/Melbourne')
            ->setTimezone(config('app.timezone', 'UTC'));

        // Client A has an active booking at 2026-10-10 14:00
        \App\Models\BookingAppointment::create([
            'bansal_appointment_id' => 9990000 + mt_rand(1, 99999),
            'client_id' => $clientA->id,
            'client_name' => 'ClientA Tester',
            'client_email' => $clientA->email,
            'appointment_datetime' => $appointmentDateTime,
            'location' => 'melbourne',
            'amount' => 150.00,
            'final_amount' => 150.00,
            'service_id' => 1,
            'noe_id' => 1,
            'status' => 'pending',
            'payment_status' => 'pending',
            'is_paid' => false,
        ]);

        \Illuminate\Support\Facades\Auth::login($clientB);

        $clientsController = app(\App\Http\Controllers\CRM\ClientsController::class);
        $request = new Request([
            'client_id' => $clientB->id,
            'service_id' => 'paid',
            'noe_id' => 1,
            'appoint_date' => '10/10/2026',
            'appoint_time' => '02:00 PM-02:30 PM',
            'location' => 'melbourne',
            'inperson_address' => 2,
            'appointment_details' => 'in_person',
            'preferred_language' => 'English',
            'description' => 'Client B attempting same slot as Client A',
            'timezone' => 'Australia/Melbourne',
        ]);

        $response = $clientsController->addAppointmentBook($request);
        $this->assertNotNull($response);
        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['status']);
        $this->assertStringContainsString('already has an appointment booked', $responseData['message']);
    }

    /** @test */
    public function test_14_14_client_cannot_self_complete_appointment()
    {
        $client = \App\Models\Admin::create([
            'first_name' => 'SelfComplete',
            'last_name' => 'Tester',
            'email' => 'self_complete_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $appointment = \App\Models\BookingAppointment::create([
            'bansal_appointment_id' => 9990000 + mt_rand(1, 99999),
            'client_id' => $client->id,
            'client_name' => 'SelfComplete Tester',
            'client_email' => $client->email,
            'appointment_datetime' => '2026-11-01 10:00:00',
            'location' => 'melbourne',
            'amount' => 150.00,
            'final_amount' => 150.00,
            'service_id' => 1,
            'noe_id' => 1,
            'status' => 'confirmed',
            'payment_status' => 'completed',
            'is_paid' => true,
        ]);

        \Illuminate\Support\Facades\Auth::guard('admin')->login($client);

        $bookingController = app(\App\Http\Controllers\CRM\BookingAppointmentsController::class);
        $request = new Request([
            'status' => 'completed',
        ]);

        $response = $bookingController->updateStatus($request, $appointment->id);
        $this->assertNotNull($response);
        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Clients cannot complete appointments', $responseData['message']);

        // Appointment in database must remain unchanged
        $appointment->refresh();
        $this->assertEquals('confirmed', $appointment->status);
    }

    /** @test */
    public function test_14_15_sync_updates_payment_and_status_for_existing_appointments()
    {
        $client = \App\Models\Admin::create([
            'first_name' => 'Sync',
            'last_name' => 'Tester',
            'email' => 'sync_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $bansalId = 9990000 + mt_rand(1, 99999);
        $appointment = \App\Models\BookingAppointment::create([
            'bansal_appointment_id' => $bansalId,
            'client_id' => $client->id,
            'client_name' => 'Sync Tester',
            'client_email' => $client->email,
            'appointment_datetime' => '2026-12-01 10:00:00',
            'location' => 'melbourne',
            'amount' => 150.00,
            'final_amount' => 150.00,
            'service_id' => 1,
            'noe_id' => 1,
            'status' => 'pending',
            'payment_status' => 'pending',
            'is_paid' => false,
        ]);

        // Mock BansalApiClient to return updated payment and confirmed status for this appointment
        $mockApiClient = $this->createMock(\App\Services\BansalAppointmentSync\BansalApiClient::class);
        $mockApiClient->method('getRecentAppointments')->willReturn([
            [
                'id' => $bansalId,
                'full_name' => 'Sync Tester',
                'email' => $client->email,
                'status' => 'confirmed',
                'is_paid' => true,
                'payment' => [
                    'status' => 'completed',
                    'payment_method' => 'stripe',
                    'paid_at' => '2026-12-01 09:00:00',
                ],
                'amount' => 150.00,
                'final_amount' => 150.00,
                'service_id' => 1,
                'noe_id' => 1,
                'appointment_datetime' => '2026-12-01 10:00:00',
            ],
        ]);

        $syncService = new \App\Services\BansalAppointmentSync\AppointmentSyncService(
            $mockApiClient,
            app(\App\Services\BansalAppointmentSync\ClientMatchingService::class),
            app(\App\Services\BansalAppointmentSync\ConsultantAssignmentService::class)
        );

        $stats = $syncService->syncRecentAppointments(10);
        $this->assertEquals(1, $stats['updated']);

        $appointment->refresh();
        $this->assertTrue((bool) $appointment->is_paid);
        $this->assertEquals('completed', $appointment->payment_status);
        $this->assertEquals('confirmed', $appointment->status);
        $this->assertEquals('stripe', $appointment->payment_method);
        $this->assertNotNull($appointment->paid_at);
        $this->assertEquals('synced', $appointment->sync_status);
    }

    /** @test */
    public function test_14_16_front_desk_checkin_enforces_today_appointment()
    {
        $staff = \App\Models\Staff::create([
            'first_name' => 'Reception',
            'last_name' => 'Staff',
            'email' => 'reception_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => null,
            'office_id' => null,
        ]);

        $client = \App\Models\Admin::create([
            'first_name' => 'Visitor',
            'last_name' => 'Client',
            'email' => 'visitor_' . uniqid() . '@example.com',
            'phone' => '0412345678',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        // Future appointment (tomorrow)
        $futureAppt = \App\Models\BookingAppointment::create([
            'bansal_appointment_id' => 9990000 + mt_rand(1, 99999),
            'client_id' => $client->id,
            'client_name' => 'Visitor Client',
            'client_email' => $client->email,
            'appointment_datetime' => now()->addDays(2),
            'location' => 'melbourne',
            'amount' => 150.00,
            'final_amount' => 150.00,
            'service_id' => 1,
            'noe_id' => 1,
            'status' => 'confirmed',
            'payment_status' => 'completed',
            'is_paid' => true,
        ]);

        \Illuminate\Support\Facades\Auth::guard('admin')->login($staff);
        config(['crm_access.exempt_staff_ids' => [$staff->id]]);

        $controller = app(\App\Http\Controllers\CRM\FrontDeskCheckInController::class);

        // Submitting with future appointment ID should be rejected with 422
        $request = new Request([
            'phone' => '0412345678',
            'email' => $client->email,
            'admin_id' => $client->id,
            'admin_type' => 'client',
            'appointment_id' => $futureAppt->id,
            'claimed_appointment' => true,
            'visit_reason' => 'appointment_followup',
        ]);

        $response = $controller->submit($request);
        $this->assertNotNull($response);
        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('not scheduled for today', $responseData['message']);

        // Today appointment should be accepted
        $todayAppt = \App\Models\BookingAppointment::create([
            'bansal_appointment_id' => 9990000 + mt_rand(1, 99999),
            'client_id' => $client->id,
            'client_name' => 'Visitor Client',
            'client_email' => $client->email,
            'appointment_datetime' => now()->setTime(14, 0),
            'location' => 'melbourne',
            'amount' => 150.00,
            'final_amount' => 150.00,
            'service_id' => 1,
            'noe_id' => 1,
            'status' => 'confirmed',
            'payment_status' => 'completed',
            'is_paid' => true,
        ]);

        $validRequest = new Request([
            'phone' => '0412345678',
            'email' => $client->email,
            'admin_id' => $client->id,
            'admin_type' => 'client',
            'appointment_id' => $todayAppt->id,
            'claimed_appointment' => true,
            'visit_reason' => 'appointment_followup',
        ]);

        $validResponse = $controller->submit($validRequest);
        $this->assertNotNull($validResponse);
        $this->assertEquals(200, $validResponse->getStatusCode());
        $validData = json_decode($validResponse->getContent(), true);
        $this->assertTrue($validData['success']);
    }

    /** @test */
    public function test_14_17_office_visit_detail_escapes_html_output()
    {
        $client = \App\Models\Admin::create([
            'first_name' => '<script>alert("xss")</script>',
            'last_name' => 'Tester',
            'email' => 'xss_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'user_type' => 3,
        ]);

        $checkinLog = \App\Models\CheckinLog::create([
            'client_id' => $client->id,
            'user_id' => 2,
            'visit_purpose' => '<img src=x onerror=alert(1)>',
            'office' => 1,
            'contact_type' => '<script>alert("type")</script>',
            'status' => 0,
            'date' => now()->toDateString(),
        ]);

        \App\Models\CheckinHistory::create([
            'subject' => '<script>alert("subject")</script>',
            'description' => '<svg/onload=alert("desc")>',
            'created_by' => 2,
            'checkin_id' => $checkinLog->id,
        ]);

        $admin = \App\Models\Staff::first() ?? \App\Models\Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'Staff',
            'email' => 'admin_staff_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => null,
        ]);
        \Illuminate\Support\Facades\Auth::guard('admin')->login($admin);
        config(['crm_access.exempt_staff_ids' => [$admin->id]]);

        $controller = app(\App\Http\Controllers\CRM\OfficeVisitController::class);
        $request = new Request(['id' => $checkinLog->id]);

        $html = $controller->getcheckin($request);
        $this->assertNotEmpty($html);

        // Raw malicious script tags must NOT appear unescaped in HTML
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringNotContainsString('<script>alert("type")</script>', $html);
        $this->assertStringNotContainsString('<script>alert("subject")</script>', $html);
        $this->assertStringNotContainsString('<svg/onload=alert("desc")>', $html);

        // Escaped entities must be present
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;subject&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;svg/onload=alert(&quot;desc&quot;)&gt;', $html);
    }
}
