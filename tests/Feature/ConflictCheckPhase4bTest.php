<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Services\ConflictCheckStalenessService;
use App\Support\MatterOtherPartiesHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConflictCheckPhase0Fixtures;
use Tests\TestCase;

/**
 * Phase 4B — staleness enforcement for Clear/Waived and pipeline gate.
 *
 * @see docs/CONFLICT_CHECK_PHASE4B.md
 */
class ConflictCheckPhase4bTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = Staff::factory()->superAdmin()->create();
    }

    #[Test]
    public function clear_rejected_when_parties_changed_since_last_clear(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $this->saveClear($fixtures)->assertOk();

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' New Party',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $response = $this->saveClear($fixtures);

        $response->assertStatus(422);
        $response->assertJsonPath('error_type', 'stale');
        $this->assertTrue((bool) $response->json('staleness.is_stale'));
    }

    #[Test]
    public function clear_allowed_after_fresh_run_when_parties_unchanged(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $this->saveClear($fixtures)->assertOk();

        $response = $this->saveClear($fixtures);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    #[Test]
    public function clear_allowed_after_run_check_when_parties_changed(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $this->saveClear($fixtures)->assertOk();

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Ack Party',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $run = $this->actingAs($this->staff, 'admin')->postJson(route('clients.conflictCheck.run'), [
            'id' => $fixtures['subject_client']->id,
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);
        $run->assertOk();
        $hash = $run->json('search_hash');
        $this->assertNotEmpty($hash);

        $this->saveClear($fixtures, $hash)->assertOk();
    }

    #[Test]
    public function pipeline_warning_when_stale_clear_exists(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $this->saveClear($fixtures)->assertOk();

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Pipeline Stale',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

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
        $this->assertStringContainsString('changed since the last conflict check', (string) $response->json('conflict_warning'));
    }

    #[Test]
    public function pipeline_ok_after_new_clear_following_party_change(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $this->saveClear($fixtures)->assertOk();

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Pipeline Fresh',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $run = $this->actingAs($this->staff, 'admin')->postJson(route('clients.conflictCheck.run'), [
            'id' => $fixtures['subject_client']->id,
            'client_matter_id' => $fixtures['subject_matter']->id,
        ]);
        $run->assertOk();

        $this->saveClear($fixtures, $run->json('search_hash'))->assertOk();

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
    public function staleness_includes_search_hash_mismatch(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();

        $this->saveClear($fixtures)->assertOk();

        $reference = app(ConflictCheckStalenessService::class)
            ->findLatestClearOrWaived(
                (int) $fixtures['subject_client']->id,
                (int) $fixtures['subject_matter']->id
            );
        $this->assertNotNull($reference);

        $fixtures['subject_client']->update([
            'email' => 'changed_' . $fixtures['subject_client']->id . '@test.local',
        ]);

        $staleness = app(ConflictCheckStalenessService::class)->evaluateStaleness(
            $fixtures['subject_client']->fresh(),
            (int) $fixtures['subject_matter']->id,
            $reference
        );

        $this->assertTrue($staleness['is_stale']);
        $this->assertNotNull($staleness['reason']);
        $this->assertNotSame($reference->search_hash, $staleness['current_search_hash']);
    }

    /**
     * @param  array<string, mixed>  $fixtures
     */
    private function saveClear(array $fixtures, ?string $acknowledgedSearchHash = null)
    {
        $payload = [
            'id' => $fixtures['subject_client']->id,
            'section' => 'conflictCheckOutcome',
            'outcome' => 'clear',
            'outcome_notes' => 'Reviewed — clear to proceed.',
            'client_matter_id' => $fixtures['subject_matter']->id,
        ];

        if ($acknowledgedSearchHash !== null) {
            $payload['acknowledged_search_hash'] = $acknowledgedSearchHash;
        }

        return $this->actingAs($this->staff, 'admin')->postJson(url('/clients/save-section'), $payload);
    }
}
