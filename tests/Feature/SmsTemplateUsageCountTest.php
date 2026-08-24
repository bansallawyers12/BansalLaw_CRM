<?php

namespace Tests\Feature;

use App\Models\SmsTemplate;
use App\Services\Sms\CellcastProvider;
use App\Services\Sms\UnifiedSmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsTemplateUsageCountTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function usage_count_is_incremented_only_when_send_succeeds()
    {
        $template = SmsTemplate::create([
            'title' => 'Test Template',
            'alias' => 'test_template',
            'message' => 'Hello {name}',
            'category' => 'notification',
            'is_active' => true,
            'usage_count' => 0,
        ]);

        $successPayload = [
            'success' => true,
            'message' => 'SMS sent successfully',
            'data' => ['messages' => [['message_id' => '12345']]],
        ];

        $mockCellcast = $this->createMock(CellcastProvider::class);
        $mockCellcast->method('sendSms')->willReturn($successPayload);

        $smsManager = new UnifiedSmsManager($mockCellcast);

        $result = $smsManager->sendFromTemplate('0412345678', $template->id, ['name' => 'John']);

        $this->assertTrue($result['success']);

        $template->refresh();
        $this->assertEquals(1, $template->usage_count);
    }

    #[Test]
    public function usage_count_is_not_incremented_when_send_fails()
    {
        $template = SmsTemplate::create([
            'title' => 'Failed Template',
            'alias' => 'failed_template',
            'message' => 'Hello {name}',
            'category' => 'notification',
            'is_active' => true,
            'usage_count' => 5,
        ]);

        $failPayload = [
            'success' => false,
            'message' => 'SMS provider error',
        ];

        $mockCellcast = $this->createMock(CellcastProvider::class);
        $mockCellcast->method('sendSms')->willReturn($failPayload);

        $smsManager = new UnifiedSmsManager($mockCellcast);

        $result = $smsManager->sendFromTemplate('0412345678', $template->id, ['name' => 'Jane']);

        $this->assertFalse($result['success']);

        $template->refresh();
        $this->assertEquals(5, $template->usage_count);
    }

    #[Test]
    public function render_template_does_not_increment_usage_count()
    {
        $template = SmsTemplate::create([
            'title' => 'Render Only',
            'alias' => 'render_only',
            'message' => 'Hello {name}',
            'category' => 'notification',
            'is_active' => true,
            'usage_count' => 2,
        ]);

        $mockCellcast = $this->createMock(CellcastProvider::class);

        $smsManager = new UnifiedSmsManager($mockCellcast);
        $rendered = $smsManager->renderTemplateByAlias('render_only', ['name' => 'Bob']);

        $this->assertEquals('Hello Bob', $rendered);

        $template->refresh();
        $this->assertEquals(2, $template->usage_count);
    }
}
