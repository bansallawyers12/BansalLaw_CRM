<?php

namespace Tests\Feature;

use App\Services\ConflictCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ConflictCheckPhase3Fixtures;
use Tests\TestCase;

/**
 * Phase 3 — search coverage (false negative fixes).
 *
 * @see docs/CONFLICT_CHECK_PHASE3.md
 */
class ConflictCheckPhase3Test extends TestCase
{
    use RefreshDatabase;

    private ConflictCheckService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConflictCheckService();
    }

    #[Test]
    public function formatted_phone_on_other_client_matches_normalized_search_term(): void
    {
        $fixtures = (new ConflictCheckPhase3Fixtures())->withFormattedPhoneOnOtherClient()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count']);
        $clientIds = array_map('intval', array_column($result['matches'], 'client_id'));
        $this->assertContains((int) $fixtures['other_client']->id, $clientIds);
        $phoneMatches = array_filter($result['matches'], fn ($m) => str_starts_with((string) ($m['matched_on'] ?? ''), 'phone:'));
        $this->assertNotEmpty($phoneMatches);
    }

    #[Test]
    public function phone_only_on_client_contacts_produces_hard_match(): void
    {
        $fixtures = (new ConflictCheckPhase3Fixtures())->withPhoneOnlyOnClientContacts()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count']);
        $sources = array_column($result['matches'], 'source');
        $this->assertTrue(
            in_array('client_contact_phone', $sources, true) || in_array('admin', $sources, true),
            'Expected match via client_contact_phone or admin phone search'
        );
        $this->assertContains(
            (int) $fixtures['other_client']->id,
            array_map('intval', array_column($result['matches'], 'client_id'))
        );
    }

    #[Test]
    public function party_email_on_other_client_conflict_party_matches(): void
    {
        $fixtures = (new ConflictCheckPhase3Fixtures())->withPartyEmailOnOtherClientConflictParty()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count']);
        $emailMatches = array_filter($result['matches'], fn ($m) => str_starts_with((string) ($m['matched_on'] ?? ''), 'email:'));
        $this->assertNotEmpty($emailMatches);
        $this->assertContains(
            (int) $fixtures['other_client']->id,
            array_map('intval', array_column($result['matches'], 'client_id'))
        );
    }

    #[Test]
    public function conflict_party_emails_table_is_searched(): void
    {
        $fixtures = (new ConflictCheckPhase3Fixtures())->withPartyEmailInConflictPartyEmailsTable()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count']);
        $this->assertContains(
            (int) $fixtures['other_client']->id,
            array_map('intval', array_column($result['matches'], 'client_id'))
        );
    }

    #[Test]
    public function company_opposing_party_abn_matches_other_client_company(): void
    {
        $fixtures = (new ConflictCheckPhase3Fixtures())->withCompanyAbnMatchOnOtherClient()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count']);
        $abnMatches = array_filter($result['matches'], fn ($m) => str_starts_with((string) ($m['matched_on'] ?? ''), 'abn:'));
        $this->assertNotEmpty($abnMatches);
        $this->assertContains(
            (int) $fixtures['other_client']->id,
            array_map('intval', array_column($result['matches'], 'client_id'))
        );
    }

    #[Test]
    public function company_client_person_names_match_individual_party_name(): void
    {
        $fixtures = (new ConflictCheckPhase3Fixtures())->withCompanyClientPersonNameMatch()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count']);
        $this->assertContains(
            (int) $fixtures['other_client']->id,
            array_map('intval', array_column($result['matches'], 'client_id'))
        );
    }

    #[Test]
    public function exact_dob_on_other_client_produces_hard_match(): void
    {
        $fixtures = (new ConflictCheckPhase3Fixtures())->withDobMatchOnOtherClient()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $this->assertGreaterThanOrEqual(1, $result['match_count']);
        $dobMatches = array_filter($result['matches'], fn ($m) => str_starts_with((string) ($m['matched_on'] ?? ''), 'dob:'));
        $this->assertNotEmpty($dobMatches);
        $this->assertContains(
            (int) $fixtures['other_client']->id,
            array_map('intval', array_column($result['matches'], 'client_id'))
        );
    }

    #[Test]
    public function like_wildcard_in_party_name_does_not_match_unrelated_records(): void
    {
        $fixtures = (new ConflictCheckPhase3Fixtures())->withLikeWildcardPartyName()->get();

        $result = $this->service->run(
            $fixtures['subject_client'],
            (int) $fixtures['subject_matter']->id
        );

        $otherClientId = (int) $fixtures['other_client']->id;
        $otherClientMatches = array_filter(
            $result['matches'],
            fn ($m) => (int) ($m['client_id'] ?? 0) === $otherClientId
        );

        $this->assertSame(
            [],
            $otherClientMatches,
            'Universal Legal Services must not match party name containing % wildcard'
        );
    }

    #[Test]
    public function normalize_phone_digits_handles_au_formats(): void
    {
        $service = new ConflictCheckService();

        $this->assertSame('61412345678', $service->normalizePhoneDigits('0412 345 678'));
        $this->assertSame('61412345678', $service->normalizePhoneDigits('412345678'));
        $this->assertTrue($service->normalizePhoneDigits('0412 345 678') !== null);
    }
}
