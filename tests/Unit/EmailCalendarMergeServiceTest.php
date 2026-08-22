<?php

namespace Tests\Unit;

use App\Models\EmailLog;
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
    public function extract_events_skips_text_when_ics_is_present(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $email = new EmailLog([
            'subject' => 'Court listing',
            'message' => 'Court hearing at Federal Court on 30/08/2026',
            'text_preview' => '',
        ]);

        $ics = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nSUMMARY:Directions Hearing\nDTSTART:20260830T100000\nDTEND:20260830T110000\nLOCATION:Federal Court\nEND:VEVENT\nEND:VCALENDAR";

        $events = $this->service()->extractEvents($email, [
            ['filename' => 'invite.ics', 'content' => $ics],
        ]);

        $this->assertCount(1, $events);
        $this->assertSame('ics_attachment', $events[0]['source']);
        $this->assertFalse($events[0]['is_all_day']);
        $this->assertSame('2026-08-30 10:00:00', $events[0]['starts_at']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function extract_events_skips_text_when_ics_is_a_cancellation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $email = new EmailLog([
            'subject' => 'Court listing',
            'message' => 'Court hearing at Federal Court on 30/08/2026 at 10:00 AM',
            'text_preview' => '',
        ]);

        $ics = "BEGIN:VCALENDAR\nMETHOD:CANCEL\nBEGIN:VEVENT\nSUMMARY:Directions Hearing\nDTSTART:20260830T100000\nDTEND:20260830T110000\nSTATUS:CANCELLED\nEND:VEVENT\nEND:VCALENDAR";

        $events = $this->service()->extractEvents($email, [
            ['filename' => 'invite.ics', 'content' => $ics],
        ]);

        $this->assertSame([], $events);
    }
}
