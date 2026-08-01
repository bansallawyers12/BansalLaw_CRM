<?php

namespace Tests\Feature;

use App\Services\ConflictCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConflictCheckPhase0Fixtures;
use Tests\TestCase;

/**
 * Phase 0 — baseline tests retained for scenarios unchanged in Phase 1.
 *
 * @see docs/CONFLICT_CHECK_PHASE0.md
 * @see tests/Feature/ConflictCheckPhase1Test.php for Phase 1 behaviour
 */
class ConflictCheckPhase0Test extends TestCase
{
    use RefreshDatabase;

    private ConflictCheckService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConflictCheckService();
    }

    #[Test]
    public function linked_individual_opposing_party_on_same_matter_produces_zero_matches(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withLinkedIndividualOnSubjectMatter()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertSame(0, $result['match_count'], 'Known linked party on this matter must not count as a conflict.');
        $this->assertSame('clear', $result['suggested_outcome']);
        $this->assertSame(1, $result['party_count']);
        $this->assertNotContains(
            $fixtures['other_party_individual']->id,
            array_column($result['matches'], 'client_id'),
            'Linked other-party admin record must be excluded from matches.'
        );
    }

    #[Test]
    public function name_only_party_matches_same_identity_on_other_clients_matter(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count'], 'Name-only party should hit the mirror row on the other client.');
        $this->assertSame('pending', $result['suggested_outcome']);

        $matchedClientIds = array_filter(array_column($result['matches'], 'client_id'));
        $this->assertContains(
            (int) $fixtures['other_client']->id,
            array_map('intval', $matchedClientIds),
            'At least one match must reference the other client.'
        );

        $sources = array_column($result['matches'], 'source');
        $this->assertTrue(
            in_array('conflict_party', $sources, true) || in_array('matter_opposing_party', $sources, true),
            'Cross-client name hit should come from conflict_party and/or matter_opposing_party search.'
        );
    }

    #[Test]
    public function subject_only_check_with_no_parties_runs_on_client_details_only(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertSame(0, $result['match_count']);
        $this->assertSame('clear', $result['suggested_outcome']);
        $this->assertSame(0, $result['party_count']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString(
            'No opposing parties are saved for this matter',
            $result['warnings'][0]
        );
    }
}
