<?php

namespace Tests\Support;

use App\Models\Admin;
use App\Models\ClientConflictParty;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\ConflictPartyEmail;
use App\Support\MatterOtherPartiesHelper;

/**
 * Phase 3 edge-case fixtures — phone/email/ABN/DOB/LIKE coverage.
 */
class ConflictCheckPhase3Fixtures
{
    private ConflictCheckPhase0Fixtures $base;

    public function __construct(?string $runToken = null)
    {
        $this->base = new ConflictCheckPhase0Fixtures($runToken);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->base->get();
    }

    /**
     * Subject party phone 412345678; other client admins.phone formatted as 0412 345 678.
     */
    public function withFormattedPhoneOnOtherClient(): self
    {
        $data = $this->base->withSubjectOnlyNoParties()->get();

        MatterOtherPartiesHelper::saveParties(
            (int) $data['subject_client']->id,
            (int) $data['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Phone Party',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '412345678',
                'rep_notes' => '',
            ]]
        );

        $data['other_client']->update(['phone' => '0412 345 678']);

        return $this;
    }

    /**
     * Matching phone only on client_contacts (admins.phone is different).
     */
    public function withPhoneOnlyOnClientContacts(): self
    {
        $data = $this->base->withSubjectOnlyNoParties()->get();

        MatterOtherPartiesHelper::saveParties(
            (int) $data['subject_client']->id,
            (int) $data['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Contact Phone Party',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '412345678',
                'rep_notes' => '',
            ]]
        );

        $data['other_client']->update(['phone' => '0499887766']);

        ClientContact::create([
            'client_id' => $data['other_client']->id,
            'contact_type' => 'Mobile',
            'country_code' => '+61',
            'phone' => '0412 345 678',
        ]);

        return $this;
    }

    /**
     * Shared email on other client's conflict party (rep_email column).
     */
    public function withPartyEmailOnOtherClientConflictParty(): self
    {
        $data = $this->base->seedBase();
        $email = strtolower(ConflictCheckPhase0Fixtures::PREFIX) . '_shared_' . $this->base->get()['subject_client']->id . '@test.local';

        MatterOtherPartiesHelper::saveParties(
            (int) $data['subject_client']->id,
            (int) $data['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Email Party',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => $email,
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        MatterOtherPartiesHelper::saveParties(
            (int) $data['other_client']->id,
            (int) $data['other_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Mirror Email',
                'party_role' => 'Applicant',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => $email,
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        return $this;
    }

    /**
     * Email stored in conflict_party_emails child table on other client.
     */
    public function withPartyEmailInConflictPartyEmailsTable(): self
    {
        $data = $this->base->seedBase();
        $email = strtolower(ConflictCheckPhase0Fixtures::PREFIX) . '_child_' . $data['subject_client']->id . '@test.local';

        MatterOtherPartiesHelper::saveParties(
            (int) $data['subject_client']->id,
            (int) $data['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Child Email Party',
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => $email,
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        MatterOtherPartiesHelper::saveParties(
            (int) $data['other_client']->id,
            (int) $data['other_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => ConflictCheckPhase0Fixtures::PREFIX . ' Email Holder',
                'party_role' => 'Applicant',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $otherParty = ClientConflictParty::query()
            ->where('client_id', $data['other_client']->id)
            ->where('client_matter_id', $data['other_matter']->id)
            ->first();

        if ($otherParty) {
            ConflictPartyEmail::create([
                'conflict_party_id' => $otherParty->id,
                'email_type' => 'Personal',
                'email' => $email,
            ]);
        }

        return $this;
    }

    /**
     * Subject linked company party ABN matches other client's company record.
     */
    public function withCompanyAbnMatchOnOtherClient(): self
    {
        $data = $this->base->withLinkedCompanyOnSubjectMatter()->get();
        $delta = $data['other_party_company'];
        $delta->load('company');
        $abn = $delta->company?->ABN_number ?? '53004085616';

        $data['other_client']->update(['is_company' => 1]);
        Company::create([
            'admin_id' => $data['other_client']->id,
            'company_name' => ConflictCheckPhase0Fixtures::PREFIX . ' Other Co',
            'ABN_number' => $abn,
            'ACN' => '111222333',
        ]);

        return $this;
    }

    /**
     * Company-type client with person names in first_name/last_name columns.
     */
    public function withCompanyClientPersonNameMatch(): self
    {
        $data = $this->base->withSubjectOnlyNoParties()->get();
        $personName = 'John ' . ConflictCheckPhase0Fixtures::PREFIX . 'Smith';

        MatterOtherPartiesHelper::saveParties(
            (int) $data['subject_client']->id,
            (int) $data['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => $personName,
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        $data['other_client']->update([
            'is_company' => 1,
            'first_name' => 'John',
            'last_name' => ConflictCheckPhase0Fixtures::PREFIX . 'Smith',
            'company_name' => ConflictCheckPhase0Fixtures::PREFIX . ' Smith Pty Ltd',
        ]);

        Company::create([
            'admin_id' => $data['other_client']->id,
            'company_name' => ConflictCheckPhase0Fixtures::PREFIX . ' Smith Pty Ltd',
        ]);

        return $this;
    }

    /**
     * Exact DOB match between subject and other client.
     */
    public function withDobMatchOnOtherClient(): self
    {
        $data = $this->base->withSubjectOnlyNoParties()->get();
        $dob = '1985-06-15';

        $data['subject_client']->update(['dob' => $dob]);
        $data['other_client']->update(['dob' => $dob]);

        return $this;
    }

    /**
     * Party name with LIKE wildcards — only exact intended name should match.
     */
    public function withLikeWildcardPartyName(): self
    {
        $data = $this->base->seedBase();
        $targetName = ConflictCheckPhase0Fixtures::PREFIX . ' 100% Legal';

        MatterOtherPartiesHelper::saveParties(
            (int) $data['subject_client']->id,
            (int) $data['subject_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => $targetName,
                'party_role' => 'Respondent',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        MatterOtherPartiesHelper::saveParties(
            (int) $data['other_client']->id,
            (int) $data['other_matter']->id,
            [[
                'opposing_lead_id' => null,
                'name' => 'Universal Legal Services',
                'party_role' => 'Applicant',
                'rep_firm' => '',
                'rep_name' => '',
                'rep_email' => '',
                'rep_phone' => '',
                'rep_notes' => '',
            ]]
        );

        Admin::factory()->create([
            'first_name' => 'Decoy',
            'last_name' => 'Legal',
            'type' => 'client',
            'client_id' => ConflictCheckPhase0Fixtures::PREFIX . 'DEC',
        ]);

        return $this;
    }
}
