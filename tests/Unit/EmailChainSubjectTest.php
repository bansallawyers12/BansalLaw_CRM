<?php

namespace Tests\Unit;

use App\Services\Email\ClientEmailListService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailChainSubjectTest extends TestCase
{
    #[Test]
    public function it_strips_nested_reply_and_forward_prefixes(): void
    {
        $this->assertSame(
            'mag-ci-260078157 | 21 days will lapse on 19 may 2026',
            ClientEmailListService::normalizeThreadSubject('Re: RE: Fwd: MAG-CI-260078157 | 21 days will lapse on 19 May 2026')
        );
    }

    #[Test]
    public function it_matches_extended_subject_variants_in_same_chain(): void
    {
        $core = 'Re: MAG-CI-260078157 | 21 days will lapse on 19 May 2026';
        $extended = 'ATO Returns and Updated Offer to Settle the Matter | MAG-CI-260078157 | 21 days will lapse on 19 May 2026';
        $other = 'Action Required | Offer of Compromise to the Defendant | MAG-CI-260078157 | ZX PAINTING GROUP P/L vs ANKUR BHATIA';

        $this->assertTrue(ClientEmailListService::subjectsBelongToSameThread($core, $extended));
        $this->assertFalse(ClientEmailListService::subjectsBelongToSameThread($core, $other));
    }

    #[Test]
    public function it_uses_last_pipe_segment_as_search_needle(): void
    {
        $normalized = ClientEmailListService::normalizeThreadSubject(
            'Re: MAG-CI-260078157 | 21 days will lapse on 19 May 2026'
        );

        $this->assertSame(
            '21 days will lapse on 19 may 2026',
            ClientEmailListService::threadSubjectSearchNeedle($normalized)
        );
    }
}
