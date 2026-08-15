<?php

namespace Tests\Feature;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityFeedPaginationTest extends TestCase
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

    private function actingSuperAdmin(): Staff
    {
        $admin = Staff::create([
            'first_name' => 'Pat',
            'last_name' => 'Admin',
            'email' => 'timeline.admin@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function makeClient(): Admin
    {
        return Admin::create([
            'first_name' => 'Casey',
            'last_name' => 'Client',
            'email' => 'timeline.client@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);
    }

    private function log(int $clientId, int $staffId, string $subject, string $type, ?string $createdAt = null): ActivitiesLog
    {
        $row = new ActivitiesLog();
        $row->client_id = $clientId;
        $row->created_by = $staffId;
        $row->subject = $subject;
        $row->description = '<p>'.$subject.'</p>';
        $row->activity_type = $type;
        $row->task_status = 0;
        $row->pin = 0;
        $row->save();
        if ($createdAt) {
            ActivitiesLog::query()->where('id', $row->id)->update(['created_at' => $createdAt]);
            $row->refresh();
        }

        return $row;
    }

    #[Test]
    public function missing_id_returns_error_json(): void
    {
        $this->actingSuperAdmin();
        $response = $this->getJson('/get-activities');
        $response->assertOk();
        $response->assertJson([
            'status' => false,
            'message' => 'Client ID is required',
        ]);
    }

    #[Test]
    public function unknown_client_returns_not_found(): void
    {
        $this->actingSuperAdmin();
        $response = $this->getJson('/get-activities?id=999999');
        $response->assertOk();
        $response->assertJson([
            'status' => false,
            'message' => 'Client not found',
        ]);
    }

    #[Test]
    public function first_page_is_capped_and_reports_has_more(): void
    {
        $staff = $this->actingSuperAdmin();
        $client = $this->makeClient();
        for ($i = 1; $i <= 41; $i++) {
            $this->log($client->id, $staff->id, 'updated basic information '.$i, 'activity');
        }

        $response = $this->getJson('/get-activities?id='.$client->id.'&page=1&per_page=40');
        $response->assertOk();
        $json = $response->json();
        $this->assertTrue($json['status']);
        $this->assertCount(40, $json['data']);
        $this->assertTrue($json['has_more']);
        $this->assertSame(1, $json['page']);
        $this->assertSame(40, $json['per_page']);

        $page2 = $this->getJson('/get-activities?id='.$client->id.'&page=2&per_page=40');
        $page2->assertOk();
        $this->assertCount(1, $page2->json('data'));
        $this->assertFalse($page2->json('has_more'));
    }

    #[Test]
    public function note_filter_excludes_action_subjects(): void
    {
        $staff = $this->actingSuperAdmin();
        $client = $this->makeClient();
        $this->log($client->id, $staff->id, 'added Call Notes', 'note');
        $this->log($client->id, $staff->id, 'Set action for Jane Smith', 'note');

        $response = $this->getJson('/get-activities?id='.$client->id.'&type=note');
        $response->assertOk();
        $subjects = collect($response->json('data'))->pluck('subject')->all();
        $this->assertContains('added Call Notes', $subjects);
        $this->assertNotContains('Set action for Jane Smith', $subjects);
    }

    #[Test]
    public function document_filter_excludes_receipt_documents(): void
    {
        $staff = $this->actingSuperAdmin();
        $client = $this->makeClient();
        $this->log($client->id, $staff->id, 'uploaded Personal Document: passport', 'document');
        $this->log($client->id, $staff->id, 'added client receipt document', 'activity');

        $response = $this->getJson('/get-activities?id='.$client->id.'&type=document');
        $response->assertOk();
        $subjects = collect($response->json('data'))->pluck('subject')->all();
        $this->assertContains('uploaded Personal Document: passport', $subjects);
        $this->assertNotContains('added client receipt document', $subjects);
    }

    #[Test]
    public function keyword_and_date_filters_apply_on_the_server(): void
    {
        $staff = $this->actingSuperAdmin();
        $client = $this->makeClient();
        $kept = $this->log($client->id, $staff->id, 'updated passport information', 'activity', '2026-08-01 10:00:00');
        $this->log($client->id, $staff->id, 'updated phone numbers', 'activity', '2026-08-01 11:00:00');
        $this->log($client->id, $staff->id, 'updated passport information', 'activity', '2026-07-01 10:00:00');

        $response = $this->getJson('/get-activities?id='.$client->id.'&keyword=passport&date_from=2026-08-01&date_to=2026-08-31');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('activity_id')->all();
        $this->assertSame([$kept->id], $ids);
    }

    #[Test]
    public function activity_filter_includes_sms_and_action_subjects(): void
    {
        $staff = $this->actingSuperAdmin();
        $client = $this->makeClient();
        $this->log($client->id, $staff->id, 'sent SMS', 'sms');
        $this->log($client->id, $staff->id, 'Set action for Jane Smith', 'note');
        $this->log($client->id, $staff->id, 'added Call Notes', 'note');

        $response = $this->getJson('/get-activities?id='.$client->id.'&type=activity');
        $response->assertOk();
        $subjects = collect($response->json('data'))->pluck('subject')->all();
        $this->assertContains('sent SMS', $subjects);
        $this->assertContains('Set action for Jane Smith', $subjects);
        $this->assertNotContains('added Call Notes', $subjects);
    }

    #[Test]
    public function omitted_activity_type_defaults_to_activity_not_note(): void
    {
        $staff = $this->actingSuperAdmin();
        $client = $this->makeClient();
        $row = ActivitiesLog::create([
            'client_id' => $client->id,
            'created_by' => $staff->id,
            'subject' => 'Booking appointment status updated',
            'description' => 'status change',
        ]);
        $this->assertSame('activity', $row->fresh()->activity_type);
    }
}
