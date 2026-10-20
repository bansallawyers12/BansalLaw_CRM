<?php

namespace Tests\Unit;

use App\Services\EmailSync\ZohoImapFetcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImapPeekBodyParseTest extends TestCase
{
    #[Test]
    public function peeked_fetch_payload_keeps_uid_and_raw_message(): void
    {
        $parsed = ZohoImapFetcher::rawMessageFromFetchPayload([
            'UID',
            '4821',
            'BODY[]',
            "From: client@example.com\r\nSubject: Hello\r\n\r\nBody",
        ]);

        $this->assertSame(4821, $parsed['uid']);
        $this->assertStringContainsString('Subject: Hello', $parsed['raw']);
    }

    #[Test]
    public function payload_without_a_body_literal_is_ignored(): void
    {
        $this->assertNull(ZohoImapFetcher::rawMessageFromFetchPayload([
            'UID',
            '9',
            'FLAGS',
            ['\\Seen'],
        ]));
    }
}
