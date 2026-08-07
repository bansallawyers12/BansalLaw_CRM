<?php

namespace Tests\Unit;

use App\Services\CalendarSync\CalendarEventTitleParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarEventTitleParserTest extends TestCase
{
    #[Test]
    public function parses_file_and_matter_prefix(): void
    {
        $parsed = CalendarEventTitleParser::parse('[71 / MAT-A] Directions hearing');

        $this->assertSame('71', $parsed['file_ref']);
        $this->assertSame('MAT-A', $parsed['matter_ref']);
        $this->assertSame('Directions hearing', $parsed['title']);
    }

    #[Test]
    public function parses_file_only_prefix(): void
    {
        $parsed = CalendarEventTitleParser::parse('[42] Deadline');

        $this->assertSame('42', $parsed['file_ref']);
        $this->assertNull($parsed['matter_ref']);
        $this->assertSame('Deadline', $parsed['title']);
    }

    #[Test]
    public function leaves_plain_title_alone(): void
    {
        $parsed = CalendarEventTitleParser::parse('Team meeting');

        $this->assertNull($parsed['file_ref']);
        $this->assertNull($parsed['matter_ref']);
        $this->assertSame('Team meeting', $parsed['title']);
    }
}
