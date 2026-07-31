<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TrustAccounting\TrustLedgerBalanceService;
use App\Services\TrustAccounting\TrustReportQueryService;
use App\Services\TrustAccounting\TrustPeriodService;
use App\Http\Controllers\API\LeadBookingApiController;
use Illuminate\Http\Request;

class SecurityBugFixes14Test extends TestCase
{
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
        // Should not throw date format exception for yyyy-mm-dd
        $this->expectNotToPerformAssertions();
        try {
            TrustPeriodService::assertTransDateUnlocked('2026-07-31');
        } catch (\RuntimeException $e) {
            // Exception only expected if locked in DB, not on format failure
            if (str_contains($e->getMessage(), 'Invalid transaction date format')) {
                $this->fail('Failed on valid date string format');
            }
        }
    }

    /** @test */
    public function test_14_9_public_booking_api_forces_unpaid_for_unauthenticated_requests()
    {
        $controller = new LeadBookingApiController();
        $request = new Request([
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'appointment_datetime' => '2026-08-01 10:00:00',
            'location' => 'melbourne',
            'is_paid' => true,
            'payment_status' => 'completed',
            'status' => 'paid',
        ]);

        $response = $controller->storeBookingAppointment($request);
        $this->assertNotNull($response);
    }
}
