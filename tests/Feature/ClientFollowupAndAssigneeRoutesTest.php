<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CheckinLog;
use App\Models\Staff;
use App\Support\ClientTagStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientFollowupAndAssigneeRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function routes_are_named_and_registered_properly(): void
    {
        $this->assertSame(url('/clients/change_assignee'), route('clients.change_assignee'));
        $this->assertSame(url('/clients/removetag'), route('clients.removetag'));
        $this->assertSame(url('/clients/followup/retagfollowup'), route('clients.followup.retag'));
    }

    #[Test]
    public function change_assignee_requires_record_id(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $response = $this->getJson('/clients/change_assignee');
        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'message' => 'Record ID is required',
            ]);
    }

    #[Test]
    public function change_assignee_updates_checkin_log_assignee(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $assignee = Staff::factory()->create();
        $this->actingAs($staff, 'admin');

        $checkin = CheckinLog::create([
            'contact_type' => 'Walk-in',
            'status' => 0,
            'user_id' => null,
            'visit_purpose' => 'Consultation',
        ]);

        $response = $this->getJson('/clients/change_assignee?id=' . $checkin->id . '&assinee=' . $assignee->id);
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Assignee changed successfully',
            ]);

        $this->assertSame((int) $assignee->id, (int) $checkin->fresh()->user_id);
    }

    #[Test]
    public function change_assignee_updates_client_profile_assignee(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $assignee = Staff::factory()->create();
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@example.com',
            'type' => 'client',
            'status' => 1,
            'client_id' => 'BLC99999',
        ]);

        $response = $this->postJson('/clients/change_assignee', [
            'id' => $client->id,
            'type' => 'client',
            'assignee' => $assignee->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Assignee changed successfully',
            ]);

        $this->assertSame((int) $assignee->id, (int) $client->fresh()->user_id);
    }

    #[Test]
    public function removetag_removes_tag_from_client(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'janedoe@example.com',
            'type' => 'client',
            'status' => 1,
            'client_id' => 'BLC99998',
            'tagname' => ClientTagStorage::encode(['Priority', 'VIP'], ['Urgent']),
        ]);

        $response = $this->getJson('/clients/removetag?client_id=' . $client->id . '&tag=Priority');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tag removed successfully',
            ]);

        [$normal, $red] = ClientTagStorage::decode($client->fresh()->tagname);
        $this->assertNotContains('Priority', $normal);
        $this->assertContains('VIP', $normal);
        $this->assertContains('Urgent', $red);
    }

    #[Test]
    public function retagfollowup_merges_tags_onto_client(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Bob',
            'last_name' => 'Smith',
            'email' => 'bobsmith@example.com',
            'type' => 'lead',
            'status' => 1,
            'client_id' => 'BLL99997',
            'tagname' => ClientTagStorage::encode(['Initial Contact'], []),
        ]);

        $response = $this->postJson('/clients/followup/retagfollowup', [
            'client_id' => $client->id,
            'tag_normal' => ['Callback Requested'],
            'tag_red' => ['Hot Lead'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Follow-up retagged successfully',
            ]);

        [$normal, $red] = ClientTagStorage::decode($client->fresh()->tagname);
        $this->assertContains('Initial Contact', $normal);
        $this->assertContains('Callback Requested', $normal);
        $this->assertContains('Hot Lead', $red);
    }

    #[Test]
    public function change_assignee_rejects_nonexistent_assignee(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $checkin = CheckinLog::create([
            'contact_type' => 'Walk-in',
            'status' => 0,
            'user_id' => null,
            'visit_purpose' => 'Consultation',
        ]);

        $response = $this->getJson('/clients/change_assignee?id=' . $checkin->id . '&assinee=999999');
        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Selected staff member does not exist',
            ]);
    }

    #[Test]
    public function change_assignee_returns_404_for_nonexistent_record(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $response = $this->getJson('/clients/change_assignee?id=999999');
        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'Record not found',
            ]);
    }

    #[Test]
    public function removetag_clear_all_clears_all_tags(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Clear',
            'last_name' => 'Tags',
            'email' => 'cleartags@example.com',
            'type' => 'client',
            'status' => 1,
            'client_id' => 'BLC99996',
            'tagname' => ClientTagStorage::encode(['Priority', 'VIP'], ['Urgent']),
        ]);

        $response = $this->getJson('/clients/removetag?client_id=' . $client->id . '&clear_all=1');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tag removed successfully',
            ]);

        [$normal, $red] = ClientTagStorage::decode($client->fresh()->tagname);
        $this->assertEmpty($normal);
        $this->assertEmpty($red);
    }

    #[Test]
    public function removetag_requires_tag_name_when_not_clearing_all(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Tag',
            'last_name' => 'Validation',
            'email' => 'tagval@example.com',
            'type' => 'client',
            'status' => 1,
            'client_id' => 'BLC99995',
            'tagname' => ClientTagStorage::encode(['Priority'], []),
        ]);

        $response = $this->getJson('/clients/removetag?client_id=' . $client->id);
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Tag name is required',
            ]);
    }

    #[Test]
    public function retagfollowup_requires_client_id(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $response = $this->postJson('/clients/followup/retagfollowup', []);
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Client ID is required',
            ]);
    }
}
