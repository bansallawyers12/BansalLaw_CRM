<?php

namespace Tests\Feature;

use App\Models\SmsLog;
use App\Services\Sms\CellcastProvider;
use App\Services\Sms\TwilioProvider;
use App\Services\Sms\UnifiedSmsManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsDeliveredAtPreservationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function non_delivered_status_check_does_not_clear_existing_delivered_at()
    {
        $originalDeliveredAt = Carbon::parse('2026-08-10 10:00:00');

        $smsLog = SmsLog::create([
            'recipient_phone' => '0412345678',
            'formatted_phone' => '+61412345678',
            'message_content' => 'Test message',
            'message_type' => 'manual',
            'provider' => 'twilio',
            'provider_message_id' => 'MSG123456',
            'status' => 'delivered',
            'delivered_at' => $originalDeliveredAt,
        ]);

        $mockStatus = [
            'success' => true,
            'status' => 'failed',
        ];
        $mockCellcast = $this->createMock(CellcastProvider::class);
        $mockCellcast->method('getSmsStatus')->willReturn($mockStatus);
        $mockTwilio = $this->createMock(TwilioProvider::class);
        $mockTwilio->method('getSmsStatus')->willReturn($mockStatus);

        $smsManager = new UnifiedSmsManager($mockCellcast, $mockTwilio);

        $result = $smsManager->getDeliveryStatus($smsLog->id);

        $this->assertTrue($result['success']);

        $smsLog->refresh();
        $this->assertEquals('failed', $smsLog->status);
        $this->assertNotNull($smsLog->delivered_at);
        $this->assertEquals('2026-08-10 10:00:00', $smsLog->delivered_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function delivered_status_check_preserves_original_delivered_at()
    {
        $originalDeliveredAt = Carbon::parse('2026-08-01 12:30:00');

        $smsLog = SmsLog::create([
            'recipient_phone' => '0412345678',
            'formatted_phone' => '+61412345678',
            'message_content' => 'Test message 2',
            'message_type' => 'manual',
            'provider' => 'cellcast',
            'provider_message_id' => 'CELL789',
            'status' => 'delivered',
            'delivered_at' => $originalDeliveredAt,
        ]);

        $deliveredStatus = [
            'success' => true,
            'status' => 'delivered',
        ];
        $mockCellcast = $this->createMock(CellcastProvider::class);
        $mockCellcast->method('getSmsStatus')->willReturn($deliveredStatus);
        $mockTwilio = $this->createMock(TwilioProvider::class);
        $mockTwilio->method('getSmsStatus')->willReturn($deliveredStatus);

        $smsManager = new UnifiedSmsManager($mockCellcast, $mockTwilio);

        $result = $smsManager->getDeliveryStatus($smsLog->id);

        $this->assertTrue($result['success']);

        $smsLog->refresh();
        $this->assertEquals('delivered', $smsLog->status);
        $this->assertEquals('2026-08-01 12:30:00', $smsLog->delivered_at->format('Y-m-d H:i:s'));
    }
}
