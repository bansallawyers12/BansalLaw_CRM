<?php

namespace Tests\Feature;

use App\Models\ClientConflictParty;
use App\Models\ConflictPartyContact;
use App\Models\ConflictPartyEmail;
use App\Models\Staff;
use App\Support\MatterOtherPartiesHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConflictCheckPhase0Fixtures;
use Tests\Support\ConflictCheckPhase4aFixtures;
use Tests\TestCase;

/**
 * Phase 4A — party upsert preserves rich conflict-party data on re-save.
 *
 * @see docs/CONFLICT_CHECK_PHASE4A.md
 */
class ConflictCheckPhase4aTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = Staff::factory()->superAdmin()->create();
    }

    #[Test]
    public function resave_parties_preserves_dob_and_aliases(): void
    {
        $fixtures = (new ConflictCheckPhase4aFixtures())->withEnrichedNameOnlyParty()->get();
        $name = $fixtures['enriched_party_name'];
        $partyId = (int) $fixtures['enriched_party_id'];

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => $name,
                'party_role' => 'Respondent',
                'rep_firm' => 'Updated Firm',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $row = ClientConflictParty::findOrFail($partyId);

        $this->assertSame('1985-06-15', $row->dob?->format('Y-m-d'));
        $this->assertSame(['Frankie', 'F. Preserve'], $row->aliases);
        $this->assertSame('10 Test Street', $row->address);
        $this->assertSame('Updated Firm', $row->rep_firm_name);
        $this->assertSame((int) $fixtures['subject_matter']->id, (int) $row->client_matter_id);
    }

    #[Test]
    public function resave_parties_preserves_conflict_party_emails(): void
    {
        $fixtures = (new ConflictCheckPhase4aFixtures())->withEnrichedNameOnlyParty()->get();
        $name = $fixtures['enriched_party_name'];
        $partyId = (int) $fixtures['enriched_party_id'];
        $email = strtolower(ConflictCheckPhase0Fixtures::PREFIX) . '_frank@test.local';

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => $name,
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $this->assertSame(1, ConflictPartyEmail::where('conflict_party_id', $partyId)->count());
        $this->assertDatabaseHas('conflict_party_emails', [
            'conflict_party_id' => $partyId,
            'email' => $email,
            'email_type' => 'Work',
        ]);
    }

    #[Test]
    public function resave_parties_preserves_abn_when_not_in_payload(): void
    {
        $fixtures = (new ConflictCheckPhase4aFixtures())->withLinkedCompanyAbnParty()->get();

        $row = ClientConflictParty::query()
            ->where('client_id', $fixtures['subject_client']->id)
            ->where('client_matter_id', $fixtures['subject_matter']->id)
            ->where('opposing_lead_id', $fixtures['other_party_company']->id)
            ->firstOrFail();

        $this->assertSame('53004085616', $row->abn);

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => $fixtures['other_party_company']->id,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Delta Holdings Pty Ltd',
                'party_role' => 'Applicant',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $row->refresh();

        $this->assertSame('53004085616', $row->abn);
        $this->assertSame('Applicant', $row->party_role);
        $this->assertSame((int) $row->id, (int) ClientConflictParty::query()
            ->where('client_id', $fixtures['subject_client']->id)
            ->where('client_matter_id', $fixtures['subject_matter']->id)
            ->where('opposing_lead_id', $fixtures['other_party_company']->id)
            ->value('id'));
    }

    #[Test]
    public function removed_party_row_is_deleted(): void
    {
        $fixtures = (new ConflictCheckPhase4aFixtures())->withTwoNameOnlyParties()->get();

        $beta = ClientConflictParty::query()
            ->where('client_id', $fixtures['subject_client']->id)
            ->where('client_matter_id', $fixtures['subject_matter']->id)
            ->where('first_name', ConflictCheckPhase0Fixtures::PREFIX)
            ->where('last_name', 'Party Beta')
            ->firstOrFail();

        ConflictPartyEmail::create([
            'conflict_party_id' => $beta->id,
            'email_type' => 'Personal',
            'email' => 'beta_remove@test.local',
        ]);

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Party Alpha',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $this->assertDatabaseMissing('client_conflict_parties', ['id' => $beta->id]);
        $this->assertDatabaseMissing('conflict_party_emails', ['conflict_party_id' => $beta->id]);
        $this->assertSame(1, ClientConflictParty::query()
            ->where('client_id', $fixtures['subject_client']->id)
            ->where('client_matter_id', $fixtures['subject_matter']->id)
            ->count());
    }

    #[Test]
    public function new_party_added_without_deleting_others(): void
    {
        $fixtures = (new ConflictCheckPhase4aFixtures())->withEnrichedNameOnlyParty()->get();
        $name = $fixtures['enriched_party_name'];
        $partyId = (int) $fixtures['enriched_party_id'];

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [
                [
                    'opposing_lead_id' => null,
                    'name' => $name,
                    'party_role' => 'Respondent',
                    'rep_firm' => '',
                    'rep_name' => '',
                    'rep_email' => '',
                    'rep_phone' => '',
                    'rep_notes' => '',
                ],
                [
                    'opposing_lead_id' => null,
                    'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Party Gamma',
                    'party_role' => 'Applicant',
                    'rep_firm' => '',
                    'rep_name' => '',
                    'rep_email' => '',
                    'rep_phone' => '',
                    'rep_notes' => '',
                ],
            ]
        );

        $original = ClientConflictParty::findOrFail($partyId);

        $this->assertSame(['Frankie', 'F. Preserve'], $original->aliases);
        $this->assertSame('1985-06-15', $original->dob?->format('Y-m-d'));
        $this->assertSame(1, ConflictPartyEmail::where('conflict_party_id', $partyId)->count());
        $this->assertSame(2, ClientConflictParty::query()
            ->where('client_id', $fixtures['subject_client']->id)
            ->where('client_matter_id', $fixtures['subject_matter']->id)
            ->count());
    }

    #[Test]
    public function resave_parties_preserves_data_when_linking_lead(): void
    {
        $fixtures = (new ConflictCheckPhase4aFixtures())->withEnrichedNameOnlyParty()->get();
        $name = $fixtures['enriched_party_name'];
        $partyId = (int) $fixtures['enriched_party_id'];

        MatterOtherPartiesHelper::saveParties(
            (int) $fixtures['subject_client']->id,
            (int) $fixtures['subject_matter']->id,
            [[
                'opposing_lead_id' => $fixtures['other_party_individual']->id,
                'name' => $name,
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $row = ClientConflictParty::findOrFail($partyId);

        $this->assertSame((int) $fixtures['other_party_individual']->id, (int) $row->opposing_lead_id);
        $this->assertSame('1985-06-15', $row->dob?->format('Y-m-d'));
        $this->assertSame(['Frankie', 'F. Preserve'], $row->aliases);
        $this->assertSame(1, ClientConflictParty::query()
            ->where('client_id', $fixtures['subject_client']->id)
            ->where('client_matter_id', $fixtures['subject_matter']->id)
            ->count());
    }

    #[Test]
    public function client_level_save_uses_transaction(): void
    {
        $fixtures = (new ConflictCheckPhase0Fixtures())->withSubjectOnlyNoParties()->get();
        $clientId = (int) $fixtures['subject_client']->id;

        $createAttempts = 0;
        $listener = function () use (&$createAttempts) {
            $createAttempts++;
            if ($createAttempts === 2) {
                throw new \RuntimeException('Simulated mid-save failure');
            }
        };
        ClientConflictParty::creating($listener);

        try {
            MatterOtherPartiesHelper::saveParties($clientId, null, [
                [
                    'opposing_lead_id' => null,
                    'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Client One',
                    'party_role' => 'Respondent',
                    'rep_firm' => '',
                    'rep_name' => '',
                    'rep_email' => '',
                    'rep_phone' => '',
                    'rep_notes' => '',
                ],
                [
                    'opposing_lead_id' => null,
                    'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Client Two',
                    'party_role' => 'Applicant',
                    'rep_firm' => '',
                    'rep_name' => '',
                    'rep_email' => '',
                    'rep_phone' => '',
                    'rep_notes' => '',
                ],
            ]);
            $this->fail('Expected simulated failure was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated mid-save failure', $e->getMessage());
        } finally {
            ClientConflictParty::flushEventListeners();
        }

        $this->assertSame(
            0,
            ClientConflictParty::query()->where('client_id', $clientId)->whereNull('client_matter_id')->count()
        );
        $this->assertSame(0, ConflictPartyContact::query()->count());
        $this->assertSame(0, ConflictPartyEmail::query()->count());
    }
}
