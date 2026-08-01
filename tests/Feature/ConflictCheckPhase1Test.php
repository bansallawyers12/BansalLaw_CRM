<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Staff;
use App\Services\ConflictCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConflictCheckPhase0Fixtures;
use Tests\TestCase;

/**
 * Phase 1 — known parties suppressed; cross-client shared other-party hits are informational.
 *
 * @see docs/CONFLICT_CHECK_PHASE1.md
 */
class ConflictCheckPhase1Test extends TestCase
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

        $this->assertSame(0, $result['match_count']);
        $this->assertSame(0, $result['informational_count']);
        $this->assertSame('clear', $result['suggested_outcome']);
    }

    #[Test]
    public function linked_company_opposing_party_on_same_matter_produces_zero_conflicts(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withLinkedCompanyOnSubjectMatter()->get();

        $this->assertInstanceOf(Admin::class, $fixtures['other_party_company']);

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertSame(0, $result['match_count'], 'Known linked company must not inflate conflict count.');
        $this->assertSame('clear', $result['suggested_outcome']);
        $this->assertSame([], $result['matches']);
    }

    #[Test]
    public function name_only_party_matches_same_identity_on_other_clients_matter_as_hard_conflict(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count']);
        $this->assertSame('pending', $result['suggested_outcome']);
        $this->assertContains(
            (int) $fixtures['other_client']->id,
            array_map('intval', array_column($result['matches'], 'client_id'))
        );
        foreach ($result['matches'] as $match) {
            $this->assertSame('hard', $match['severity'] ?? null);
        }
    }

    #[Test]
    public function same_linked_opposing_party_on_two_clients_is_informational_only(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSharedLinkedOpposingPartyOnBothClients()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertSame(0, $result['match_count']);
        $this->assertSame(1, $result['informational_count'], 'Deduped cross-client shared other-party row.');
        $this->assertSame('clear', $result['suggested_outcome']);

        $info = $result['informational_matches'][0];
        $this->assertSame('informational', $info['severity'] ?? null);
        $this->assertTrue($info['is_known_party'] ?? false);
        $this->assertSame((int) $fixtures['other_client']->id, (int) ($info['client_id'] ?? 0));
        $this->assertNotContains(
            (int) $fixtures['other_party_individual']->id,
            array_column($result['informational_matches'], 'client_id')
        );
    }

    #[Test]
    public function informational_match_includes_reason_and_does_not_include_subject_party_admin_id(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSharedLinkedOpposingPartyOnBothClients()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertCount(1, $result['informational_matches']);
        $info = $result['informational_matches'][0];
        $this->assertNotEmpty($info['informational_reason'] ?? '');
        $this->assertStringContainsString('other client', strtolower((string) $info['informational_reason']));

        $allClientIds = array_merge(
            array_column($result['matches'], 'client_id'),
            array_column($result['informational_matches'], 'client_id')
        );
        $this->assertNotContains((int) $fixtures['other_party_individual']->id, array_map('intval', $allClientIds));
        $this->assertNotContains((int) $fixtures['subject_client']->id, array_map('intval', $allClientIds));
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
        $this->assertSame(0, $result['informational_count']);
        $this->assertSame('clear', $result['suggested_outcome']);
        $this->assertNotEmpty($result['warnings']);
    }

    #[Test]
    public function run_conflict_check_rejects_invalid_explicit_matter_id(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();
        $staff = Staff::factory()->superAdmin()->create();

        $response = $this->actingAs($staff, 'admin')->postJson(route('clients.conflictCheck.run'), [
            'id' => $fixtures['subject_client']->id,
            'client_matter_id' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error_type' => 'validation',
        ]);
    }

    #[Test]
    public function run_conflict_check_includes_client_matter_id_in_response(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withLinkedIndividualOnSubjectMatter()->get();
        $staff = Staff::factory()->superAdmin()->create();

        $response = $this->actingAs($staff, 'admin')->postJson(route('clients.conflictCheck.run'), [
            'id' => $fixtures['subject_client']->id,
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('client_matter_id', $fixtures['subject_matter']->id);
        $response->assertJsonPath('match_count', 0);
        $response->assertJsonStructure(['informational_matches', 'informational_count']);
    }
}
