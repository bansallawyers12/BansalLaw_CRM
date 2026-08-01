<?php

namespace Tests\Support;

use App\Models\ClientConflictParty;
use App\Models\ConflictPartyEmail;
use App\Support\MatterOtherPartiesHelper;

/**
 * Phase 4A fixtures — party upsert preservation scenarios.
 */
class ConflictCheckPhase4aFixtures
{
    private ConflictCheckPhase0Fixtures $base;

    private ?string $enrichedPartyName = null;

    private ?int $enrichedPartyId = null;

    public function __construct(?string $runToken = null)
    {
        $this->base = new ConflictCheckPhase0Fixtures($runToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $data = $this->base->get();

        if ($this->enrichedPartyName !== null) {
            $data['enriched_party_name'] = $this->enrichedPartyName;
            $data['enriched_party_id'] = $this->enrichedPartyId;
        }

        return $data;
    }

    /**
     * Name-only party on subject matter with enriched conflict-party row (DOB, aliases, email).
     */
    public function withEnrichedNameOnlyParty(): self
    {
        $data = $this->base->withSubjectOnlyNoParties()->get();
        $name = ConflictCheckPhase0Fixtures::PREFIX . ' Frank Preserve';

        MatterOtherPartiesHelper::saveParties(
            (int) $data['subject_client']->id,
            (int) $data['subject_matter']->id,
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

        $row = ClientConflictParty::query()
            ->where('client_id', $data['subject_client']->id)
            ->where('client_matter_id', $data['subject_matter']->id)
            ->firstOrFail();

        $row->update([
            'dob' => '1985-06-15',
            'aliases' => ['Frankie', 'F. Preserve'],
            'address' => '10 Test Street',
        ]);

        ConflictPartyEmail::create([
            'conflict_party_id' => $row->id,
            'email_type' => 'Work',
            'email' => strtolower(ConflictCheckPhase0Fixtures::PREFIX) . '_frank@test.local',
        ]);

        $this->enrichedPartyName = $name;
        $this->enrichedPartyId = (int) $row->id;

        return $this;
    }

    /**
     * Linked company party with ABN on conflict row (matter-scoped).
     */
    public function withLinkedCompanyAbnParty(): self
    {
        $this->base->withLinkedCompanyOnSubjectMatter();

        return $this;
    }

    /**
     * Two name-only parties on subject matter.
     */
    public function withTwoNameOnlyParties(): self
    {
        $data = $this->base->withSubjectOnlyNoParties()->get();

        MatterOtherPartiesHelper::saveParties(
            (int) $data['subject_client']->id,
            (int) $data['subject_matter']->id,
            [
                [
                    'opposing_lead_id' => null,
                    'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Party Alpha',
                    'party_role' => 'Respondent',
                    'rep_firm' => '',
                    'rep_name' => '',
                    'rep_email' => '',
                    'rep_phone' => '',
                    'rep_notes' => '',
                ],
                [
                    'opposing_lead_id' => null,
                    'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Party Beta',
                    'party_role' => 'Applicant',
                    'rep_firm' => '',
                    'rep_name' => '',
                    'rep_email' => '',
                    'rep_phone' => '',
                    'rep_notes' => '',
                ],
            ]
        );

        return $this;
    }
}
