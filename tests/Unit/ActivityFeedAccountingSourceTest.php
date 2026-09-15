<?php

namespace Tests\Unit;

use App\Support\ActivityFeedAccountingSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityFeedAccountingSourceTest extends TestCase
{
    #[Test]
    public function parse_subject_detects_invoice_reference(): void
    {
        $meta = ActivityFeedAccountingSource::parseSubject('added invoice. Reference no- INV-007');

        $this->assertNotNull($meta);
        $this->assertSame(ActivityFeedAccountingSource::KIND_INVOICE, $meta['kind']);
        $this->assertSame('INV-007', $meta['reference']);
    }

    #[Test]
    public function parse_subject_detects_office_receipt_reference(): void
    {
        $meta = ActivityFeedAccountingSource::parseSubject('added office receipt. Reference no- OR-12');

        $this->assertNotNull($meta);
        $this->assertSame(ActivityFeedAccountingSource::KIND_OFFICE_RECEIPT, $meta['kind']);
        $this->assertSame('OR-12', $meta['reference']);
    }

    #[Test]
    public function parse_subject_returns_null_without_reference(): void
    {
        $this->assertNull(ActivityFeedAccountingSource::parseSubject('added a note'));
    }
}
