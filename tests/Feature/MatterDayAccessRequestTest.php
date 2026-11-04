<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientAccessGrant;
use App\Models\ClientMatter;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\UserRole;
use App\Support\MatterScopedAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatterDayAccessRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'crm_access.allocation_enabled' => true,
            'crm_access.matter_scoped_access_enabled' => true,
            'crm_access.assignment_only_access_enabled' => true,
            'crm_access.matter_temp_manager_names' => ['Ajay Bansal', 'Khushi Sangroya'],
            'crm_access.matter_temp_manager_role_ids' => [1],
        ]);

        UserRole::firstOrCreate(['id' => 1], ['name' => 'Super Admin']);
        UserRole::firstOrCreate(['id' => 16], ['name' => 'Solicitor']);
    }

    #[Test]
    public function restricted_matter_screen_offers_a_one_day_request(): void
    {
        [$requester, $client, $blocked] = $this->matterPair();

        $this->actingAs($requester, 'admin');

        $encodeId = base64_encode(convert_uuencode((string) $client->id));
        $response = $this->get('/clients/detail/' . $encodeId . '/' . $blocked->client_unique_matter_no);

        $response->assertOk();
        $response->assertSee('Access to this matter is restricted', false);
        $response->assertSee('Khushi, Ajay, or a Super Admin can approve one day of access to this matter.', false);
        $response->assertSee('Request access', false);
        $response->assertDontSee('Ask a Super Admin to grant you access', false);
    }

    #[Test]
    public function approval_opens_only_that_matter_for_one_day_and_leaves_the_team_unchanged(): void
    {
        [$requester, $client, $blocked, $open, $lawyer, $untouched] = $this->matterPair();
        $khushi = Staff::factory()->create([
            'status' => 1,
            'role' => 16,
            'first_name' => 'Khushi',
            'last_name' => 'Sangroya',
        ]);
        $ajay = Staff::factory()->create([
            'status' => 1,
            'role' => 16,
            'first_name' => 'Ajay',
            'last_name' => 'Bansal',
        ]);
        $superAdmin = Staff::factory()->create([
            'status' => 1,
            'role' => 1,
            'first_name' => 'Office',
            'last_name' => 'Admin',
        ]);
        $bystander = Staff::factory()->create([
            'status' => 1,
            'role' => 16,
            'first_name' => 'Sam',
            'last_name' => 'Lee',
        ]);

        $this->actingAs($requester, 'admin');
        $request = $this->postJson(route('crm.access.matter-day'), [
            'client_matter_id' => $blocked->id,
            'reason_code' => 'cover',
        ]);
        $request->assertOk();
        $grantId = (int) $request->json('grant_id');

        $encodeId = base64_encode(convert_uuencode((string) $client->id));
        $this->get('/clients/detail/' . $encodeId . '/' . $blocked->client_unique_matter_no)
            ->assertOk()
            ->assertSee('Request sent. Khushi, Ajay, or a Super Admin can approve one day of access to this matter.', false);

        $grant = ClientAccessGrant::query()->findOrFail($grantId);
        $this->assertSame('pending', $grant->status);
        $this->assertSame(MatterScopedAccess::GRANT_TYPE_MATTER_TEMP, $grant->grant_type);
        $this->assertNull(MatterScopedAccess::parseRoleFromGrantNote($grant->requester_note));
        $this->assertFalse(MatterScopedAccess::canAccessClientMatter((int) $blocked->id, $requester));

        $this->postJson(route('crm.access.matter-day'), [
            'client_matter_id' => $blocked->id,
            'reason_code' => 'cover',
        ])->assertStatus(422);

        $this->actingAs($bystander, 'admin');
        $this->postJson(route('crm.access.approve', ['grant' => $grantId]))
            ->assertStatus(422);
        $this->getJson(route('crm.access.queue.data'))->assertForbidden();

        $this->actingAs($khushi, 'admin');
        $queue = $this->getJson(route('crm.access.queue.data'));
        $queue->assertOk();
        $queue->assertJsonFragment(['id' => $grantId]);
        $queue->assertJsonFragment(['requester_note' => 'One day on ' . $blocked->client_unique_matter_no]);

        $this->actingAs($requester, 'admin');
        $this->postJson(route('crm.access.approve', ['grant' => $grantId]))
            ->assertStatus(422);

        $this->actingAs($khushi, 'admin');
        $this->postJson(route('crm.access.approve', ['grant' => $grantId]))
            ->assertOk();

        $grant->refresh();
        $blocked->refresh();
        $this->assertSame('active', $grant->status);
        $this->assertSame('1_day', $grant->quick_reason_code);
        $this->assertSame((int) $khushi->id, (int) $grant->approved_by_staff_id);
        $this->assertNotNull($grant->starts_at);
        $this->assertNotNull($grant->ends_at);
        $this->assertSame(24 * 60, (int) $grant->starts_at->diffInMinutes($grant->ends_at, true));
        $this->assertSame((int) $lawyer->id, (int) $blocked->sel_person_responsible);
        $this->assertTrue(MatterScopedAccess::canAccessClientMatter((int) $blocked->id, $requester));
        $this->assertTrue(MatterScopedAccess::canAccessClientMatter((int) $open->id, $requester));
        $this->assertFalse(MatterScopedAccess::canAccessClientMatter((int) $untouched->id, $requester));

        $this->assertTrue(
            Notification::query()->where('receiver_id', $khushi->id)->where('notification_type', 'access_request')->exists()
        );
        $this->assertTrue(
            Notification::query()->where('receiver_id', $ajay->id)->where('notification_type', 'access_request')->exists()
        );
        $this->assertTrue(
            Notification::query()->where('receiver_id', $superAdmin->id)->where('notification_type', 'access_request')->exists()
        );
        $this->assertFalse(
            Notification::query()->where('receiver_id', $bystander->id)->where('notification_type', 'access_request')->exists()
        );

        $result = Notification::query()
            ->where('receiver_id', $requester->id)
            ->where('notification_type', 'access_request_approved')
            ->first();
        $this->assertNotNull($result);
        $this->assertStringContainsString('one day', (string) $result->message);
        $this->assertStringContainsString($blocked->client_unique_matter_no, (string) $result->url);
    }

    #[Test]
    public function a_person_cannot_stack_more_than_the_pending_limit(): void
    {
        config(['crm_access.max_pending_supervisor_requests' => 2]);

        $requester = Staff::factory()->create(['status' => 1, 'role' => 16, 'first_name' => 'Cover', 'last_name' => 'Solicitor']);
        $lawyer = Staff::factory()->create(['status' => 1, 'role' => 16, 'first_name' => 'Lead', 'last_name' => 'Lawyer']);
        $client = Admin::factory()->create(['type' => 'client']);
        $this->actingAs($requester, 'admin');

        foreach ([1, 2, 3] as $n) {
            $matter = ClientMatter::query()->create([
                'client_id' => $client->id,
                'matter_status' => 1,
                'client_unique_matter_no' => 'CAP_' . $n,
                'sel_person_responsible' => $lawyer->id,
            ]);
            $response = $this->postJson(route('crm.access.matter-day'), [
                'client_matter_id' => $matter->id,
                'reason_code' => 'urgent',
            ]);
            if ($n < 3) {
                $response->assertOk();
            } else {
                $response->assertStatus(422);
            }
        }

        $this->assertSame(2, ClientAccessGrant::query()->where('staff_id', $requester->id)->where('status', 'pending')->count());
    }

    /**
     * @return array{0: Staff, 1: Admin, 2: ClientMatter, 3: ClientMatter, 4: Staff, 5: ClientMatter}
     */
    private function matterPair(): array
    {
        $lawyer = Staff::factory()->create(['status' => 1, 'role' => 16, 'first_name' => 'Lead', 'last_name' => 'Lawyer']);
        $requester = Staff::factory()->create(['status' => 1, 'role' => 16, 'first_name' => 'Cover', 'last_name' => 'Solicitor']);
        $client = Admin::factory()->create([
            'type' => 'client',
            'first_name' => 'Gurdeep',
            'last_name' => 'Singh',
        ]);

        $open = ClientMatter::query()->create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CRM_1',
            'sel_person_responsible' => $requester->id,
        ]);
        $blocked = ClientMatter::query()->create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CRM_2',
            'sel_person_responsible' => $lawyer->id,
        ]);
        $untouched = ClientMatter::query()->create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CRM_3',
            'sel_person_responsible' => $lawyer->id,
        ]);

        return [$requester, $client, $blocked, $open, $lawyer, $untouched];
    }
}
