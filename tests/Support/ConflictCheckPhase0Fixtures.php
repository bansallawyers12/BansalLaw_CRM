<?php

namespace Tests\Support;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Company;
use App\Models\Staff;
use App\Support\MatterOtherPartiesHelper;
use Illuminate\Support\Facades\Auth;

/**
 * Deterministic fixture set for Phase 0 conflict-check behaviour locking.
 *
 * All records use the CCP0 prefix so they are easy to spot in QA databases.
 */
class ConflictCheckPhase0Fixtures
{
    public const PREFIX = 'CCP0';

    /** @var array<string, mixed> */
    private array $data = [];

    private string $runToken;

    public function __construct(?string $runToken = null)
    {
        $this->runToken = $runToken ?? substr(str_replace('.', '', uniqid('', true)), -10);
    }

    /**
     * @return array{
     *     staff: Staff,
     *     subject_client: Admin,
     *     other_client: Admin,
     *     other_party_individual: Admin,
     *     other_party_company: Admin|null,
     *     subject_matter: ClientMatter,
     *     other_matter: ClientMatter
     * }
     */
    public function seedBase(): array
    {
        if ($this->data !== []) {
            return $this->data;
        }

        $staff = Staff::factory()->create([
            'email' => strtolower(self::PREFIX) . "_staff_{$this->runToken}@test.local",
        ]);
        Auth::guard('admin')->login($staff);

        $subjectClient = Admin::factory()->create([
            'first_name' => self::PREFIX,
            'last_name' => 'Alice Subject',
            'email' => strtolower(self::PREFIX) . "_alice_{$this->runToken}@test.local",
            'phone' => '0400001001',
            'type' => 'client',
            'client_id' => self::PREFIX . 'ALIC' . $this->runToken,
        ]);

        $otherClient = Admin::factory()->create([
            'first_name' => self::PREFIX,
            'last_name' => 'Bob Holder',
            'email' => strtolower(self::PREFIX) . "_bob_{$this->runToken}@test.local",
            'phone' => '0400001002',
            'type' => 'client',
            'client_id' => self::PREFIX . 'BOBH' . $this->runToken,
        ]);

        $otherPartyIndividual = Admin::factory()->create([
            'first_name' => self::PREFIX,
            'last_name' => 'Charlie Opponent',
            'email' => strtolower(self::PREFIX) . "_charlie_{$this->runToken}@test.local",
            'phone' => '0400002001',
            'type' => 'lead',
            'is_other_party' => 1,
            'client_id' => self::PREFIX . 'CHAR' . $this->runToken,
        ]);

        $subjectMatter = ClientMatter::create([
            'client_id' => $subjectClient->id,
            'matter_status' => 1,
            'sel_matter_id' => 1,
            'client_unique_matter_no' => self::PREFIX . '-SUB-' . $this->runToken,
        ]);

        $otherMatter = ClientMatter::create([
            'client_id' => $otherClient->id,
            'matter_status' => 1,
            'sel_matter_id' => 1,
            'client_unique_matter_no' => self::PREFIX . '-OTH-' . $this->runToken,
        ]);

        $this->data = [
            'staff' => $staff,
            'subject_client' => $subjectClient,
            'other_client' => $otherClient,
            'other_party_individual' => $otherPartyIndividual,
            'other_party_company' => null,
            'subject_matter' => $subjectMatter,
            'other_matter' => $otherMatter,
        ];

        return $this->data;
    }

    private function ensureCompanyOtherParty(): Admin
    {
        $base = $this->seedBase();

        if ($base['other_party_company'] instanceof Admin) {
            return $base['other_party_company'];
        }

        $otherPartyCompany = Admin::factory()->create([
            'first_name' => 'Contact',
            'last_name' => self::PREFIX . ' Delta',
            'email' => strtolower(self::PREFIX) . "_delta_{$this->runToken}@test.local",
            'phone' => '0400002002',
            'type' => 'lead',
            'is_other_party' => 1,
            'is_company' => 1,
            'client_id' => self::PREFIX . 'DELT' . $this->runToken,
        ]);

        Company::create([
            'admin_id' => $otherPartyCompany->id,
            'company_name' => self::PREFIX . ' Delta Holdings Pty Ltd',
            'ABN_number' => '53004085616',
            'ACN' => '004085616',
        ]);

        $this->data['other_party_company'] = $otherPartyCompany;

        return $otherPartyCompany;
    }

    /**
     * Subject matter: one linked individual other party (opposing_lead_id set).
     */
    public function withLinkedIndividualOnSubjectMatter(): self
    {
        $base = $this->seedBase();

        MatterOtherPartiesHelper::saveParties(
            (int) $base['subject_client']->id,
            (int) $base['subject_matter']->id,
            [[
                'opposing_lead_id' => $base['other_party_individual']->id,
                'name' => self::PREFIX . ' Charlie Opponent',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        return $this;
    }

    /**
     * Subject matter: one linked company other party (opposing_lead_id set, is_company).
     */
    public function withLinkedCompanyOnSubjectMatter(): self
    {
        $base = $this->seedBase();
        $otherPartyCompany = $this->ensureCompanyOtherParty();

        MatterOtherPartiesHelper::saveParties(
            (int) $base['subject_client']->id,
            (int) $base['subject_matter']->id,
            [[
                'opposing_lead_id' => $otherPartyCompany->id,
                'name' => self::PREFIX . ' Delta Holdings Pty Ltd',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        return $this;
    }

    /**
     * Subject matter: name-only party row (no opposing_lead_id).
     * Other client: same name on their matter (also name-only) for a real cross-client hit.
     */
    public function withNameOnlyPartyAndMirrorOnOtherClient(): self
    {
        $base = $this->seedBase();
        $name = self::PREFIX . ' Eve Nameonly';

        MatterOtherPartiesHelper::saveParties(
            (int) $base['subject_client']->id,
            (int) $base['subject_matter']->id,
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

        MatterOtherPartiesHelper::saveParties(
            (int) $base['other_client']->id,
            (int) $base['other_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => $name,
                'party_role' => 'Applicant',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        return $this;
    }

    /**
     * Same linked other-party record on subject and other client's active matters.
     */
    public function withSharedLinkedOpposingPartyOnBothClients(): self
    {
        $base = $this->seedBase();
        $partyPayload = [[
            'opposing_lead_id' => $base['other_party_individual']->id,
            'name' => self::PREFIX . ' Charlie Opponent',
            'party_role' => 'Respondent',
            'rep_firm' => '',
            'rep_name' => '',
            'rep_email' => '',
            'rep_phone' => '',
            'rep_notes' => '',
        ]];

        MatterOtherPartiesHelper::saveParties(
            (int) $base['subject_client']->id,
            (int) $base['subject_matter']->id,
            $partyPayload
        );

        MatterOtherPartiesHelper::saveParties(
            (int) $base['other_client']->id,
            (int) $base['other_matter']->id,
            $partyPayload
        );

        return $this;
    }

    /**
     * Subject client with an active matter but no opposing parties saved.
     */
    public function withSubjectOnlyNoParties(): self
    {
        $this->seedBase();

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->data;
    }
}
