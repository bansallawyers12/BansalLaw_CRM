<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use App\Services\Email\ClientEmailUploadDuplicateService;
use App\Services\EmailSync\ManualUploadThreadMatchService;
use App\Services\EmailSync\UnassignedEmailUploadMatchService;
use Tests\TestCase;

class ClientEmailUploadDuplicateServiceTest extends TestCase
{
    public function test_location_label_for_sent_on_client_matter(): void
    {
        $service = new ClientEmailUploadDuplicateService(
            $this->createMock(UnassignedEmailUploadMatchService::class),
            $this->createMock(ManualUploadThreadMatchService::class),
        );

        $email = new EmailLog([
            'mail_body_type' => 'sent',
            'client_id' => 60,
        ]);

        $this->assertSame('client_sent', $service->locationKey($email));
        $this->assertSame('Client matter → Sent', $service->locationLabel($email));
    }
}
