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
}
