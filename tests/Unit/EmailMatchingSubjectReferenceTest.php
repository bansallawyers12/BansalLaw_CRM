<?php

namespace Tests\Unit;

use App\Services\EmailMatchingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailMatchingSubjectReferenceTest extends TestCase
{
    #[Test]
    public function it_extracts_bansal_client_ids_and_matter_codes_from_subject(): void
    {
        $service = new EmailMatchingService();
        $subject = "RE: 000 and ROI | Gurdeep Singh Cheema | GURD2600002 / CRM_2 | Adjourned to 2 December 2026";

        $this->assertContains('GURD2600002', $service->extractClientReferences($subject));
        $this->assertContains('CRM_2', $service->extractMatterReferences($subject));
        $this->assertSame([
            ['client_ref' => 'GURD2600002', 'matter_ref' => 'CRM_2'],
        ], $service->extractClientMatterPairs($subject));
    }

    #[Test]
    public function it_extracts_our_ref_client_and_matter_pair(): void
    {
        $service = new EmailMatchingService();
        $subject = 'Re: Letter of Demand Process | Our ref RAMA2600168 / CIV_1 | Your ref V123';

        $this->assertContains('RAMA2600168', $service->extractClientReferences($subject));
        $this->assertContains('CIV_1', $service->extractMatterReferences($subject));
        $this->assertSame([
            ['client_ref' => 'RAMA2600168', 'matter_ref' => 'CIV_1'],
        ], $service->extractClientMatterPairs($subject));
    }

    #[Test]
    public function it_still_extracts_legacy_cli_client_ids(): void
    {
        $service = new EmailMatchingService();

        $this->assertContains('CLI12345', $service->extractClientReferences('Matter CLI12345 update'));
    }

    #[Test]
    public function it_extracts_client_name_candidates_from_pipe_subjects(): void
    {
        $service = new EmailMatchingService();
        $subject = 'RE: 000 and ROI | Gurdeep Singh Cheema | Adjourned to 2 December 2026';

        $this->assertContains('Gurdeep Singh Cheema', $service->extractNameCandidates($subject));
        $this->assertNotContains('Adjourned to 2 December 2026', $service->extractNameCandidates($subject));
    }

    #[Test]
    public function it_does_not_treat_plain_sentences_as_client_ids(): void
    {
        $service = new EmailMatchingService();

        $this->assertSame([], $service->extractClientReferences('Re: Hearing tomorrow at Sunshine Magistrates Court'));
        $this->assertSame([], $service->extractClientMatterPairs('Re: Hearing tomorrow at Sunshine Magistrates Court'));
    }

    #[Test]
    public function it_resolves_unique_matter_when_only_one_is_active(): void
    {
        $service = new EmailMatchingService();
        $matters = [
            ['id' => 10, 'matter_no' => 'CRM_2', 'matter_title' => 'Criminal Law', 'matter_active' => true],
            ['id' => 11, 'matter_no' => 'CRM_3', 'matter_title' => 'Criminal Law', 'matter_active' => false],
            ['id' => 12, 'matter_no' => 'CRM_1', 'matter_title' => 'Criminal Law', 'matter_active' => false],
        ];

        $resolved = $service->resolveUniqueAssignableMatter($matters);

        $this->assertNotNull($resolved);
        $this->assertSame(10, $resolved['id']);
        $this->assertNull($service->resolveUniqueAssignableMatter([
            ['id' => 1, 'matter_active' => true],
            ['id' => 2, 'matter_active' => true],
        ]));
    }

    #[Test]
    public function it_resolves_unique_matter_when_client_has_only_one_matter(): void
    {
        $service = new EmailMatchingService();
        $matters = [
            ['id' => 99, 'matter_no' => 'CIV_1', 'matter_title' => 'Civil', 'matter_active' => false],
        ];

        $resolved = $service->resolveUniqueAssignableMatter($matters);

        $this->assertNotNull($resolved);
        $this->assertSame(99, $resolved['id']);
    }

    #[Test]
    public function it_offers_only_active_matters_when_staff_must_choose(): void
    {
        $service = new EmailMatchingService();
        $matters = [
            ['id' => 1, 'matter_active' => true],
            ['id' => 2, 'matter_active' => true],
            ['id' => 3, 'matter_active' => false],
        ];

        $choice = $service->mattersForStaffChoice($matters);

        $this->assertCount(2, $choice);
        $this->assertSame([1, 2], array_column($choice, 'id'));

        $allInactive = [
            ['id' => 4, 'matter_active' => false],
            ['id' => 5, 'matter_active' => false],
        ];
        $this->assertSame($allInactive, $service->mattersForStaffChoice($allInactive));
    }
}
