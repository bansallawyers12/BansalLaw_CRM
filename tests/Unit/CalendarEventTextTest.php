<?php

namespace Tests\Unit;

use App\Support\CalendarEventText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarEventTextTest extends TestCase
{
    #[Test]
    public function extract_location_ignores_at_email_patterns(): void
    {
        $this->assertNull(CalendarEventText::extractLocationCandidate(
            'Pawan Malhotra — Hearing @ mailto:admin@admin.com.au'
        ));
        $this->assertNull(CalendarEventText::extractLocationCandidate(
            'Hearing at Admin@barrislawyers.au on 30/08/2026'
        ));
        $this->assertNull(CalendarEventText::sanitizeLocation('this stage, and you may proceed'));
    }

    #[Test]
    public function extract_location_keeps_court_names(): void
    {
        $location = CalendarEventText::extractLocationCandidate(
            'Directions hearing at Federal Circuit and Family Court on 30/08/2026 at 10:00 AM'
        );

        $this->assertNotNull($location);
        $this->assertStringContainsStringIgnoringCase('federal', $location);
    }
}
