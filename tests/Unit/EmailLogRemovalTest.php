<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailLogRemovalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function removed_emails_are_hidden_from_mail_lists(): void
    {
        $visible = EmailLog::query()->create([
            'subject' => 'Client update',
            'from_mail' => 'client@example.com',
            'type' => 'client',
            'mail_type' => 1,
        ]);
        $removed = EmailLog::query()->create([
            'subject' => 'Weekly newsletter',
            'from_mail' => 'news@example.com',
            'type' => 'client',
            'mail_type' => 1,
            'removed_at' => now(),
            'removal_reason' => EmailLog::REMOVAL_MARKETING,
        ]);

        $query = EmailLog::query();
        EmailLog::applyExcludeCalendarInvitesFromMailLists($query);
        $ids = $query->pluck('id')->all();

        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($removed->id, $ids);
    }
}
