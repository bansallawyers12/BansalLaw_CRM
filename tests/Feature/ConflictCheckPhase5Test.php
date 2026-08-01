<?php

namespace Tests\Feature;

use App\Models\ClientConflictCheck;
use App\Models\Staff;
use App\Services\ConflictCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConflictCheckPhase0Fixtures;
use Tests\TestCase;

/**
 * Phase 5 — UX polish: force_clear, access-gated links, history detail, pipeline tightening.
 *
 * @see docs/CONFLICT_CHECK_PHASE5.md
 */
class ConflictCheckPhase5Test extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = Staff::factory()->superAdmin()->create();
    }

    #[Test]
    public function force_clear_with_sufficient_notes_saves_clear_despite_matches(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient()->get();

        $run = $this->actingAs($this->staff, 'admin')->postJson(route('clients.conflictCheck.run'), [
            'id' => $fixtures['subject_client']->id,
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);
        $run->assertOk();
        $this->assertGreaterThan(0, (int) $run->json('match_count'));

        $response = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'section' => 'conflictCheckOutcome',
            'outcome' => 'clear',
            'outcome_notes' => 'Reviewed all matches — no actual conflict after partner sign-off.',
            'client_matter_id' => $fixtures['subject_matter']->id,
            'force_clear' => '1',
            'acknowledged_search_hash' => $run->json('search_hash'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('force_clear_applied', true);
        $this->assertSame(1, ClientConflictCheck::count());
        $this->assertSame('clear', ClientConflictCheck::first()->outcome);
    }

    #[Test]
    public function clear_without_override_still_rejected_when_matches_exist(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient()->get();

        $response = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'section' => 'conflictCheckOutcome',
            'outcome' => 'clear',
            'outcome_notes' => 'Too short',
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_type', 'validation');
        $this->assertGreaterThan(0, (int) $response->json('match_count'));
    }

    #[Test]
    public function restricted_staff_sees_match_without_detail_url_for_inaccessible_client(): void
    {
        config(['crm_access.allocation_enabled' => true]);

        $fixtures = (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient()->get();
        $restricted = Staff::factory()->regularStaff()->create();

        $fixtures['subject_client']->update(['user_id' => $restricted->id]);
        $fixtures['subject_matter']->update(['sel_legal_practitioner' => $restricted->id]);
        $fixtures['other_client']->update(['user_id' => $this->staff->id]);
        $fixtures['other_matter']->update(['sel_legal_practitioner' => $this->staff->id]);

        Auth::guard('admin')->login($restricted);

        $result = app(ConflictCheckService::class)->run(
            $fixtures['subject_client']->fresh(),
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThan(0, $result['match_count']);

        $otherMatch = collect($result['matches'])->first(
            fn ($m) => (int) ($m['client_id'] ?? 0) === (int) $fixtures['other_client']->id
        );

        $this->assertNotNull($otherMatch);
        $this->assertTrue($otherMatch['access_locked'] ?? false);
        $this->assertNull($otherMatch['detail_url'] ?? null);
    }

    #[Test]
    public function conflict_check_detail_endpoint_returns_stored_matches(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withNameOnlyPartyAndMirrorOnOtherClient()->get();

        $run = $this->actingAs($this->staff, 'admin')->postJson(route('clients.conflictCheck.run'), [
            'id' => $fixtures['subject_client']->id,
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);
        $run->assertOk();

        $save = $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), [
            'id' => $fixtures['subject_client']->id,
            'section' => 'conflictCheckOutcome',
            'outcome' => 'conflict_found',
            'outcome_notes' => 'Recorded conflict for review and partner decision.',
            'client_matter_id' => $fixtures['subject_matter']->id,
            'acknowledged_search_hash' => $run->json('search_hash'),
        ]);
        $save->assertOk();

        $checkId = (int) $save->json('check_id');
        $this->assertGreaterThan(0, $checkId);

        $detail = $this->actingAs($this->staff, 'admin')->getJson(
            route('clients.conflictCheck.detail', ['checkId' => $checkId])
            . '?client_id=' . $fixtures['subject_client']->id
        );

        $detail->assertOk();
        $detail->assertJsonPath('success', true);
        $this->assertGreaterThan(0, count($detail->json('check.matches') ?? []));
        $this->assertNotEmpty($detail->json('check.search_hash'));
        $this->assertNotEmpty($detail->json('check.checked_at'));
        $firstMatch = $detail->json('check.matches.0');
        $this->assertNotEmpty($firstMatch['detail_url'] ?? null);
    }

    #[Test]
    public function conflict_check_detail_rejects_mismatched_client_id(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $check = ClientConflictCheck::create([
            'client_id' => $fixtures['subject_client']->id,
            'client_matter_id' => $fixtures['subject_matter']->id,
            'checked_by' => $this->staff->id,
            'checked_at' => now(),
            'search_terms' => ['subject' => []],
            'matches' => [],
            'informational_matches' => [],
            'match_count' => 0,
            'informational_count' => 0,
            'outcome' => 'clear',
            'outcome_notes' => 'Test',
        ]);

        $detail = $this->actingAs($this->staff, 'admin')->getJson(
            route('clients.conflictCheck.detail', ['checkId' => $check->id])
            . '?client_id=' . $fixtures['other_client']->id
        );

        $detail->assertStatus(404);
    }

    #[Test]
    public function pipeline_ignores_legacy_null_matter_clear_for_specific_matter(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        ClientConflictCheck::create([
            'client_id' => $fixtures['subject_client']->id,
            'client_matter_id' => null,
            'checked_by' => $this->staff->id,
            'checked_at' => now(),
            'search_terms' => ['subject' => []],
            'matches' => [],
            'informational_matches' => [],
            'match_count' => 0,
            'informational_count' => 0,
            'parties_snapshot_at' => now(),
            'search_hash' => hash('sha256', 'legacy'),
            'outcome' => 'clear',
            'outcome_notes' => 'Legacy client-wide clear',
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
        $this->assertNotEmpty($response->json('conflict_warning'));
        $this->assertStringContainsString('No conflict check', (string) $response->json('conflict_warning'));
    }
}
