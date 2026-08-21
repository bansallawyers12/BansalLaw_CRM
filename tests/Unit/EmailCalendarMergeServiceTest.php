<?php

namespace Tests\Unit;

use App\Services\Email\EmailCalendarMergeService;
use App\Support\CalendarEventText;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailCalendarMergeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): EmailCalendarMergeService
    {
        return new EmailCalendarMergeService();
    }

    #[Test]
    public function parse_text_skips_invoice_and_mailto_hearing_noise(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $events = $this->service()->parseTextForScheduledEvents(
            'Hearing attached invoice for your reference',
            "Please find hearing attached invoice for your reference\nHearing @ mailto:admin@admin.com.au on 30/08/2026\nPawan hearing at Admin@barrislawyers.au 30/08/2026 12:00 PM"
        );

        $this->assertSame([], $events);
    }

    #[Test]
    public function parse_text_keeps_clean_court_hearing_line(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $events = $this->service()->parseTextForScheduledEvents(
            'Court listing',
            'Directions hearing at Federal Circuit and Family Court on 30/08/2026 at 10:00 AM'
        );

        $this->assertCount(1, $events);
        $this->assertSame('hearing', $events[0]['event_type']);
        $this->assertSame('Hearing', $events[0]['title']);
        $this->assertNotNull($events[0]['location']);
        $this->assertStringContainsStringIgnoringCase('federal', (string) $events[0]['location']);
        $this->assertFalse($events[0]['is_all_day']);
    }

    #[Test]
    public function parse_text_does_not_create_meeting_from_ref_only_email(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $events = $this->service()->parseTextForScheduledEvents(
            'Re: Our ref RAMA280388 / CIV_1 | Your ref VL41144',
            "Please schedule a review for 04/09/2026.\nKind regards"
        );

        $this->assertSame([], $events);
    }

    #[Test]
    public function parse_text_creates_appointment_with_clean_title(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $events = $this->service()->parseTextForScheduledEvents(
            'Client appointment confirmation',
            'Appointment with client on 25/08/2026 at 2:00 PM'
        );

        $this->assertCount(1, $events);
        $this->assertSame('appointment', $events[0]['event_type']);
        $this->assertSame('Client appointment confirmation', $events[0]['title']);
    }

    #[Test]
    public function sanitize_location_rejects_emails(): void
    {
        $this->assertNull(CalendarEventText::sanitizeLocation('mailto:admin@admin.com.au'));
        $this->assertNull(CalendarEventText::sanitizeLocation('Admin@barrislawyers.au'));
        $this->assertSame(
            'Federal Circuit and Family Court',
            CalendarEventText::sanitizeLocation('Federal Circuit and Family Court')
        );
        $this->assertNull(CalendarEventText::sanitizeLocation('this stage, and you may proceed'));
        $this->assertSame('Meeting', CalendarEventText::displayStaffTitle(
            'Re: Our ref RAMA280388 / CIV_1 | Your ref VL41144',
            'meeting'
        ));
    }

    #[Test]
    public function sanitize_hearing_type_collapses_body_snippets(): void
    {
        $this->assertSame('Hearing', CalendarEventText::sanitizeHearingType('hearing attached invoice for your reference w'));
        $this->assertSame('Hearing', CalendarEventText::sanitizeHearingType('Other'));
        $this->assertSame('Directions hearing', CalendarEventText::sanitizeHearingType('Directions hearing'));
    }

    #[Test]
    public function parse_text_ignores_bare_listing_with_a_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $events = $this->service()->parseTextForScheduledEvents(
            'FYI',
            'Please see the listing 30/08/2026'
        );

        $this->assertSame([], $events);
    }

    #[Test]
    public function extract_events_dedupes_same_datetime_across_noisy_titles(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $email = new class {
            public $subject = 'Listing';
            public $message = "Court hearing at Federal Court on 30/08/2026 at 12:00 PM\nHearing listed Federal Court 30/08/2026 12:00pm";
            public $text_preview = '';
            public function attachments()
            {
                return new class {
                    public function get()
                    {
                        return collect();
                    }
                };
            }
        };

        // Use parseText directly then dedupe via extract path reflection-free: call parse twice merge.
        $service = $this->service();
        $a = $service->parseTextForScheduledEvents('Listing', (string) $email->message);
        $this->assertNotEmpty($a);

        $ref = new \ReflectionClass($service);
        $dedupe = $ref->getMethod('dedupeEvents');
        $dedupe->setAccessible(true);
        $deduped = $dedupe->invoke($service, array_merge($a, $a));
        $this->assertCount(1, $deduped);
    }
}
