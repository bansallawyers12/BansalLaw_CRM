<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailLogPlainTextPreviewTest extends TestCase
{
    #[Test]
    public function style_blocks_are_removed_from_list_preview(): void
    {
        $html = '<style>.zclet__header__info a, .zclet__header__title a, .zclet__event__text a { word-break: break-all; }</style>'
            . '<div>Team meeting tomorrow at 10:00 AM</div>';

        $preview = EmailLog::plainTextPreview($html, 90);

        $this->assertStringNotContainsString('.zclet__header__info', $preview);
        $this->assertStringNotContainsString('word-break', $preview);
        $this->assertStringContainsString('Team meeting tomorrow at 10:00 AM', $preview);
    }

    #[Test]
    public function tags_are_stripped_and_entities_decoded(): void
    {
        $html = '<p>Hello &amp; welcome</p><br><b>Bansal</b>';

        $this->assertSame(
            'Hello & welcome Bansal',
            EmailLog::plainTextPreview($html, 100)
        );
    }

    #[Test]
    public function calendar_invite_subjects_are_detected(): void
    {
        $this->assertTrue(EmailLog::isCalendarInviteSubject('Invitation: Directions Hearing'));
        $this->assertTrue(EmailLog::isCalendarInviteSubject('Accepted: Team Meeting'));
        $this->assertTrue(EmailLog::isCalendarInviteSubject('Updated Invitation: Conference'));
        $this->assertFalse(EmailLog::isCalendarInviteSubject('Re: Matter update for client'));
        $this->assertFalse(EmailLog::isCalendarInviteSubject('Evidence.com - Evidence Download Link'));
    }

    #[Test]
    public function hearing_notice_subjects_are_detected(): void
    {
        $this->assertTrue(EmailLog::isHearingNoticeSubject('Hearing Listed on 24 June 2026 at 10:00am'));
        $this->assertTrue(EmailLog::isHearingNoticeSubject('Divorce Hearing | 30 July 2026'));
        $this->assertTrue(EmailLog::isHearingNoticeSubject('RE: Compliance directions hearing 19 June 2026'));
        $this->assertTrue(EmailLog::isHearingNoticeSubject('Matter is listed for an In-Person Hearing on 24 June 2026'));
        $this->assertFalse(EmailLog::isHearingNoticeSubject('Re: Matter update for client'));
        $this->assertFalse(EmailLog::isHearingNoticeSubject('Evidence.com - Evidence Download Link'));
    }

    #[Test]
    public function calendar_payload_detection_matches_ics_dumps(): void
    {
        $ics = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nSUMMARY:Hearing\nEND:VEVENT\nEND:VCALENDAR";
        $this->assertTrue(EmailLog::isCalendarPayload($ics));
        $this->assertFalse(EmailLog::isCalendarPayload('Please see the attached documents.'));
    }
}
