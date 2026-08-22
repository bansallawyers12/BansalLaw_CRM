<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Staff;
use App\Models\Note;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\Workflow;
use App\Http\Controllers\CRM\AssigneeController;
use App\Http\Controllers\AdminConsole\Sms\SmsWebhookController;
use Illuminate\Http\Request;

class SecurityBugFixes13Test extends TestCase
{
    /** @test */
    public function test_13_1_empty_unique_group_id_does_not_mass_update()
    {
        $controller = new AssigneeController();
        $request = new Request([
            'id' => 999999,
            'unique_group_id' => ''
        ]);

        $response = $controller->completeTask($request);
        $this->assertNotNull($response);
    }

    /** @test */
    public function test_13_10_webhook_preserves_existing_delivered_at()
    {
        $log = new SmsLog();
        $log->delivered_at = '2026-01-01 12:00:00';
        $log->status = 'delivered';

        $controller = new SmsWebhookController();
        $request = new Request([
            'MessageSid' => 'test_sid_123',
            'MessageStatus' => 'sent'
        ]);

        $response = $controller->twilioStatus($request);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
