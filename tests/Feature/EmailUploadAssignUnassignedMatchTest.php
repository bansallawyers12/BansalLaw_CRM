<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\EmailLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailUploadAssignUnassignedMatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function staff_can_assign_unassigned_match_to_client_matter(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'FAM_1',
            'matter_status' => 1,
        ]);

        $unassigned = EmailLog::query()->create([
            'subject' => 'Manual upload duplicate test',
            'from_mail' => 'a@example.com',
            'to_mail' => 'b@example.com',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => 100,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('email.upload.assign-unassigned-match'), [
                'email_log_id' => $unassigned->id,
                'client_id' => $client->id,
                'client_matter_id' => $matter->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $unassigned->refresh();
        $this->assertSame((int) $client->id, (int) $unassigned->client_id);
        $this->assertSame((int) $matter->id, (int) $unassigned->client_matter_id);
        $this->assertSame('manual_assigned', $unassigned->sync_assignment_status);
    }
}
