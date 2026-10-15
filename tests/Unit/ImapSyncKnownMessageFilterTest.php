<?php

namespace Tests\Unit;

use App\Services\EmailSync\ImapSyncKnownMessageFilter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImapSyncKnownMessageFilterTest extends TestCase
{
    #[Test]
    public function normalize_message_id_strips_angle_brackets(): void
    {
        $filter = new ImapSyncKnownMessageFilter;

        $this->assertSame('abc@zoho.com', $filter->normalizeMessageId('<abc@zoho.com>'));
        $this->assertSame('plain-id', $filter->normalizeMessageId('plain-id'));
    }
}
