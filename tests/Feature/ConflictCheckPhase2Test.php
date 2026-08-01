<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientConflictCheck;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Services\ConflictCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConflictCheckPhase0Fixtures;
use Tests\TestCase;

/**
 * Phase 2 — server-authoritative save, matter-scoped records, pipeline gate.
 *
 * @see docs/CONFLICT_CHECK_PHASE2.md
 */
class ConflictCheckPhase2Test extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = Staff::factory()->superAdmin()->create();
    }

    #[Test]
    public function cannot_save_clear_when_server_finds_hard_matches(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient()->get();

        $response = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'section' => 'conflictCheckOutcome',
            'outcome' => 'clear',
            'outcome_notes' => 'Should be rejected',
            'client_matter_id' => $fixtures['subject_matter']->id,
            'matches' => '[]',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_type', 'validation');
        $this->assertGreaterThan(0, (int) $response->json('match_count'));
        $this->assertSame(0, ClientConflictCheck::count());
    }

    #[Test]
    public function stripped_client_matches_are_ignored_server_rejects_clear(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient()->get();

        $service = new ConflictCheckService();
        $live = $service->run($fixtures['subject_client'], (int) $fixtures['subject_matter']->id);
        $this->assertGreaterThan(0, $live['match_count']);

        $response = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'section' => 'conflictCheckOutcome',
            'outcome' => 'clear',
            'outcome_notes' => 'Client tried to strip matches',
            'client_matter_id' => $fixtures['subject_matter']->id,
            'matches' => '[]',
            'search_terms' => json_encode(['tampered' => true]),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, ClientConflictCheck::count());
    }

    #[Test]
    public function informational_only_allows_clear_outcome_save(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSharedLinkedOpposingPartyOnBothClients()->get();

        $response = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'section' => 'conflictCheckOutcome',
            'outcome' => 'clear',
            'outcome_notes' => 'Informational only — no hard conflicts.',
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('match_count', 0);
        $response->assertJsonPath('informational_count', 1);

        $check = ClientConflictCheck::first();
        $this->assertNotNull($check);
        $this->assertSame('clear', $check->outcome);
        $this->assertSame((int) $fixtures['subject_matter']->id, (int) $check->client_matter_id);
        $this->assertSame(0, (int) $check->match_count);
        $this->assertSame(1, (int) $check->informational_count);
        $this->assertNotEmpty($check->search_hash);
    }

    #[Test]
    public function clear_on_matter_a_does_not_satisfy_pipeline_for_matter_b(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $matterB = ClientMatter::create([
            'client_id' => $fixtures['subject_client']->id,
            'matter_status' => 1,
            'sel_matter_id' => 1,
            'client_unique_matter_no' => 'CCP0-MAT-B',
        ]);

        ClientConflictCheck::create([
            'client_id' => $fixtures['subject_client']->id,
            'client_matter_id' => $fixtures['subject_matter']->id,
            'checked_by' => $this->staff->id,
            'checked_at' => now(),
            'outcome' => 'clear',
            'match_count' => 0,
            'informational_count' => 0,
            'matches' => [],
            'informational_matches' => [],
        ]);

        $fixtures['subject_client']->update(['type' => 'lead', 'lead_status' => 'conflict_check']);

        $response = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'type' => 'lead',
            'section' => 'leadPipeline',
            'lead_status' => 'engaged',
            'client_matter_id' => $matterB->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('conflict_warning'));
    }

    #[Test]
    public function clear_on_matter_a_satisfies_pipeline_when_same_matter_active(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        ClientConflictCheck::create([
            'client_id' => $fixtures['subject_client']->id,
            'client_matter_id' => $fixtures['subject_matter']->id,
            'checked_by' => $this->staff->id,
            'checked_at' => now(),
            'outcome' => 'clear',
            'match_count' => 0,
            'informational_count' => 0,
            'matches' => [],
            'informational_matches' => [],
        ]);

        $fixtures['subject_client']->update(['type' => 'lead', 'lead_status' => 'conflict_check']);

        $response = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'type' => 'lead',
            'section' => 'leadPipeline',
            'lead_status' => 'engaged',
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);

        $response->assertOk();
        $this->assertEmpty($response->json('conflict_warning'));
    }

    #[Test]
    public function outcome_save_persists_server_search_metadata(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withLinkedIndividualOnSubjectMatter()->get();

        $response = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'section' => 'conflictCheckOutcome',
            'outcome' => 'clear',
            'outcome_notes' => 'No conflicts after server re-run.',
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);

        $response->assertOk();

        $check = ClientConflictCheck::first();
        $this->assertNotNull($check);
        $this->assertIsArray($check->search_terms);
        $this->assertSame((int) $fixtures['subject_matter']->id, (int) $check->client_matter_id);
        $this->assertNotEmpty($check->search_hash);
    }
}
