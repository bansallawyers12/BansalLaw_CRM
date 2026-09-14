<?php

namespace Tests\Unit;

use App\Services\EmailSync\ZohoImapFetcher;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ZohoImapSentFolderResolutionTest extends TestCase
{
    #[Test]
    public function it_matches_sent_folder_names_case_insensitively(): void
    {
        $fetcher = new ZohoImapFetcher();
        $method = new ReflectionMethod(ZohoImapFetcher::class, 'folderNameLooksLikeSent');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($fetcher, 'Sent'));
        $this->assertTrue($method->invoke($fetcher, 'sent items'));
        $this->assertTrue($method->invoke($fetcher, 'SENT MESSAGES'));
        $this->assertTrue($method->invoke($fetcher, 'Sent Mail'));
        $this->assertFalse($method->invoke($fetcher, 'INBOX'));
        $this->assertFalse($method->invoke($fetcher, 'Drafts'));
    }
}
