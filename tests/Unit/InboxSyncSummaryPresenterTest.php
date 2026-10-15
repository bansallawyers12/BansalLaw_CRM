<?php

namespace Tests\Unit;

use App\Services\EmailSync\InboxSyncSummaryPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InboxSyncSummaryPresenterTest extends TestCase
{
    #[Test]
    public function format_mailbox_console_line_uses_already_in_crm_label(): void
    {
        $line = InboxSyncSummaryPresenter::formatMailboxConsoleLine('user@example.com', [
            'imported' => 1,
            'skipped' => 80,
            'failed' => 0,
        ]);

        $this->assertSame('user@example.com: imported=1, already_in_crm=80, failed=0', $line);
    }

    #[Test]
    public function with_presentation_aliases_mirrors_skipped_counts(): void
    {
        $summary = InboxSyncSummaryPresenter::withPresentationAliases([
            'total_imported' => 0,
            'total_skipped' => 5,
            'mailboxes' => [
                'a@example.com' => ['imported' => 0, 'skipped' => 5, 'failed' => 0],
            ],
        ]);

        $this->assertSame(5, $summary['total_already_in_crm']);
        $this->assertSame(5, $summary['mailboxes']['a@example.com']['already_in_crm']);
        $this->assertSame(5, $summary['total_skipped']);
    }
}
