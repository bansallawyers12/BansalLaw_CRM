<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Services\MatterTeamOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatterTeamOverviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function blank_assignee_ids_show_as_unassigned_not_hardcoded_names(): void
    {
        if (! Schema::hasTable('client_matters')) {
            $this->markTestSkipped('client_matters table not available');
        }

        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CIV_1',
            'sel_legal_practitioner' => null,
            'sel_person_responsible' => null,
            'sel_person_assisting' => null,
            'office_id' => null,
        ]);

        $payload = MatterTeamOverview::buildFromMatter($matter->fresh());

        $this->assertSame((int) $matter->id, $payload['client_matter_id']);
        $this->assertSame('CIV_1', $payload['matter_reference']);

        foreach ($payload['team'] as $slot) {
            $this->assertSame('unassigned', $slot['status'], $slot['key']);
            $this->assertNull($slot['name']);
        }

        $names = array_column($payload['team'], 'name');
        $this->assertNotContains('Ajay Bansal', $names);
        $this->assertNotContains('Michael Saleh', $names);
        $this->assertNotContains('Khushi Sangroya', $names);
        $this->assertNotContains('Melbourne', $names);

        $roles = array_column($payload['team'], 'role');
        $this->assertSame(
            ['Principal Solicitor', 'Responsible Solicitor', 'Paralegal', 'Handling office'],
            $roles
        );
        $staffSlots = array_slice($payload['team'], 0, 3);
        $this->assertSame(
            array_column($staffSlots, 'role'),
            array_column($staffSlots, 'role_title'),
            'Visible label and tooltip must stay the same title'
        );
    }

    #[Test]
    public function assigned_staff_and_office_are_shown_with_initials(): void
    {
        if (! Schema::hasTable('client_matters')) {
            $this->markTestSkipped('client_matters table not available');
        }

        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $lp = Staff::factory()->create(['first_name' => 'Priya', 'last_name' => 'Nguyen']);
        $pr = Staff::factory()->create(['first_name' => 'Sam', 'last_name' => 'Lee']);
        $pa = Staff::factory()->create(['first_name' => 'Alex', 'last_name' => 'Chen']);

        $office = new Branch();
        $office->office_name = 'Sydney';
        $office->save();

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'FAM_9',
            'sel_legal_practitioner' => $lp->id,
            'sel_person_responsible' => $pr->id,
            'sel_person_assisting' => $pa->id,
            'office_id' => $office->id,
        ]);

        $payload = MatterTeamOverview::buildFromMatter(
            $matter->fresh()->load(['legalPractitioner', 'personResponsible', 'personAssisting', 'office'])
        );

        $this->assertSame('assigned', $payload['team'][0]['status']);
        $this->assertSame('Priya Nguyen', $payload['team'][0]['name']);
        $this->assertSame('PN', $payload['team'][0]['initials']);

        $this->assertSame('Sam Lee', $payload['team'][1]['name']);
        $this->assertSame('Alex Chen', $payload['team'][2]['name']);
        $this->assertSame('Sydney', $payload['team'][3]['name']);
        $this->assertTrue($payload['team'][3]['is_office']);
    }

    #[Test]
    public function missing_staff_record_shows_no_longer_on_file(): void
    {
        if (! Schema::hasTable('client_matters')) {
            $this->markTestSkipped('client_matters table not available');
        }

        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CIV_2',
            'sel_legal_practitioner' => 999999,
            'sel_person_responsible' => null,
            'sel_person_assisting' => null,
            'office_id' => 999999,
        ]);

        $payload = MatterTeamOverview::buildFromMatter($matter->fresh());

        $this->assertSame('missing', $payload['team'][0]['status']);
        $this->assertNull($payload['team'][0]['name']);
        $this->assertSame('unassigned', $payload['team'][1]['status']);
        $this->assertSame('missing', $payload['team'][3]['status']);
    }

    #[Test]
    public function staff_with_blank_name_is_not_shown_as_assigned(): void
    {
        if (! Schema::hasTable('client_matters')) {
            $this->markTestSkipped('client_matters table not available');
        }

        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $lp = Staff::factory()->create(['first_name' => '   ', 'last_name' => '']);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CIV_BLANK',
            'sel_legal_practitioner' => $lp->id,
        ]);

        $payload = MatterTeamOverview::buildFromMatter($matter->fresh());

        $this->assertSame('missing', $payload['team'][0]['status']);
        $this->assertNull($payload['team'][0]['name']);
    }

    #[Test]
    public function case_detail_with_colon_stays_one_block(): void
    {
        if (! Schema::hasTable('client_matters') || ! Schema::hasColumn('client_matters', 'case_detail')) {
            $this->markTestSkipped('case_detail column not available');
        }

        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CIV_3',
            'case_detail' => "Hearing: adjourned to 12 May\nSecond line kept together",
            'incidence_type' => 'Other',
        ]);

        $payload = MatterTeamOverview::buildFromMatter($matter->fresh());

        $caseRows = $payload['case_details'];
        $labels = array_column($caseRows, 'label');
        $this->assertContains('Matter subtype', $labels);
        $this->assertContains('Case detail', $labels);
        $this->assertNotContains('Hearing', $labels);

        $caseDetail = collect($caseRows)->firstWhere('label', 'Case detail');
        $this->assertNotNull($caseDetail);
        $this->assertSame('full', $caseDetail['kind']);
        $this->assertStringContainsString('Hearing: adjourned to 12 May', $caseDetail['value']);
        $this->assertStringContainsString('Second line kept together', $caseDetail['value']);
    }

    #[Test]
    public function for_client_resolves_matter_by_reference(): void
    {
        if (! Schema::hasTable('client_matters')) {
            $this->markTestSkipped('client_matters table not available');
        }

        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'OLD_1',
        ]);
        $wanted = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CIV_1',
        ]);

        $payload = MatterTeamOverview::forClient((int) $client->id, 'CIV_1');

        $this->assertNotNull($payload);
        $this->assertSame((int) $wanted->id, $payload['client_matter_id']);
        $this->assertSame('CIV_1', $payload['matter_reference']);
    }

    #[Test]
    public function for_client_returns_null_when_no_matters(): void
    {
        if (! Schema::hasTable('client_matters')) {
            $this->markTestSkipped('client_matters table not available');
        }

        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);

        $this->assertNull(MatterTeamOverview::forClient((int) $client->id, null));
    }
}
