<?php

namespace Tests\Unit;

use App\Support\InvoiceWorkDate;
use PHPUnit\Framework\TestCase;

class InvoiceWorkDateTest extends TestCase
{
    public function test_start_date_from_single_slash_date(): void
    {
        $this->assertSame('14/09/2026', InvoiceWorkDate::startDate('14/09/2026'));
        $this->assertSame('19/05/2026', InvoiceWorkDate::startDate('19/05/2026'));
    }

    public function test_start_date_from_iso_date(): void
    {
        $this->assertSame('19/05/2026', InvoiceWorkDate::startDate('2026-05-19'));
    }

    public function test_format_line_date_for_storage_single_and_range(): void
    {
        $this->assertSame('19/05/2026', InvoiceWorkDate::formatLineDateForStorage('19/05/2026'));
        $this->assertSame('19/05/2026 – 29/09/2026', InvoiceWorkDate::formatLineDateForStorage('19/05/2026 – 29/09/2026'));
        $this->assertSame('19/05/2026', InvoiceWorkDate::formatLineDateForStorage('2026-05-19'));
    }

    public function test_start_date_from_range(): void
    {
        $this->assertSame('22/06/2026', InvoiceWorkDate::startDate('22/06/2026 – 16/07/2026'));
        $this->assertSame('07/07/2026', InvoiceWorkDate::dueDate('22/06/2026 – 16/07/2026'));
    }

    public function test_start_date_from_named_month(): void
    {
        $this->assertSame('28/05/2026', InvoiceWorkDate::startDate('28 May 2026'));
        $this->assertSame('22/06/2026', InvoiceWorkDate::startDate('22 Jun – 16 Jul 2026'));
    }

    public function test_header_date_empty_is_na(): void
    {
        $this->assertSame('N/A', InvoiceWorkDate::headerDate(''));
        $this->assertSame('N/A', InvoiceWorkDate::dueDate(''));
    }
}
