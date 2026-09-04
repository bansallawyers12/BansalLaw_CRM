<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\ClientMatterTask;
use App\Models\Note;
use App\Models\Staff;
use App\Services\ClientMatterTaskSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientMatterTaskAddedByTest extends TestCase
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
    public function mirroring_action_note_sets_created_by_to_note_creator_not_assignee(): void
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $creator = Staff::factory()->create(['role' => 1, 'status' => 1, 'first_name' => 'Sonu', 'last_name' => 'Kumar']);
        $assignee = Staff::factory()->create(['role' => 2, 'status' => 1, 'first_name' => 'Kabir', 'last_name' => 'Makkar']);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'FAM_1',
            'matter_status' => 1,
        ]);

        $note = Note::create([
            'client_id' => $client->id,
            'user_id' => $creator->id,
            'matter_id' => $matter->id,
            'description' => 'divorce application submission',
            'is_action' => 1,
            'type' => 'client',
            'task_group' => 'Review',
            'assigned_to' => $assignee->id,
            'status' => '0',
            'pin' => 0,
            'action_date' => now()->toDateString(),
            'unique_group_id' => 'group_test_added_by',
        ]);

        $task = app(ClientMatterTaskSyncService::class)->mirrorTaskNoteToClientTask($note);

        $this->assertNotNull($task);
        $this->assertSame($creator->id, (int) $task->created_by);
        $this->assertNotSame($assignee->id, (int) $task->created_by);
    }

    #[Test]
    public function tasks_index_repairs_created_by_mismatched_with_linked_note_creator(): void
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $creator = Staff::factory()->superAdmin()->create(['status' => 1, 'first_name' => 'Sonu', 'last_name' => 'Kumar']);
        $assignee = Staff::factory()->create(['role' => 2, 'status' => 1, 'first_name' => 'Kabir', 'last_name' => 'Makkar']);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'FAM_1',
            'matter_status' => 1,
        ]);

        $note = Note::create([
            'client_id' => $client->id,
            'user_id' => $creator->id,
            'matter_id' => $matter->id,
            'description' => 'divorce application submission',
            'is_action' => 1,
            'type' => 'client',
            'task_group' => 'Review',
            'assigned_to' => $assignee->id,
            'status' => '0',
            'pin' => 0,
            'action_date' => now()->toDateString(),
            'unique_group_id' => 'group_test_repair',
        ]);

        $task = ClientMatterTask::create([
            'client_matter_id' => $matter->id,
            'client_id' => $client->id,
            'title' => 'divorce application submission',
            'is_done' => false,
            'sort_order' => 1,
            'created_by' => $assignee->id, // buggy historical value
            'note_id' => $note->id,
        ]);

        $response = $this->actingAs($creator, 'admin')
            ->getJson('/clients/matter-tasks?client_id=' . $client->id . '&matter_id=' . $matter->id);

        $response->assertOk()->assertJson(['status' => true]);
        $task->refresh();
        $this->assertSame($creator->id, (int) $task->created_by);

        $payload = $response->json('data');
        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);
        $this->assertSame($creator->id, (int) ($payload[0]['created_by'] ?? 0));
        $this->assertSame('Sonu', $payload[0]['creator']['first_name'] ?? null);
        $this->assertSame('Kabir', $payload[0]['assignee']['first_name'] ?? null);
    }
}
