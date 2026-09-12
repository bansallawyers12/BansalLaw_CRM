<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function canonical_task_route_names_resolve(): void
    {
        $this->assertSame(url('/tasks'), route('assignee.tasks'));
        $this->assertSame(url('/tasks/completed'), route('assignee.tasks.completed'));
        $this->assertSame(url('/tasks/list'), route('tasks.list'));
        $this->assertSame(url('/tasks/counts'), route('tasks.counts'));
        $this->assertSame(url('/tasks/complete'), route('tasks.complete'));
        $this->assertSame(url('/tasks/reopen'), route('tasks.reopen'));
        $this->assertSame(url('/tasks/update'), route('tasks.update'));
        $this->assertSame(url('/dashboard/tasks/complete'), route('dashboard.tasks.complete'));
        $this->assertSame(url('/clients/tasks/store'), route('clients.tasks.store'));
        $this->assertSame(url('/clients/tasks/personal/store'), route('clients.tasks.personal.store'));
        $this->assertSame(url('/clients/tasks/update'), route('clients.tasks.update'));
        $this->assertSame(url('/clients/tasks/reassign'), route('clients.tasks.reassign'));
    }

    #[Test]
    public function legacy_action_get_urls_redirect_to_tasks(): void
    {
        $staff = new Staff();
        $staff->id = 501;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        $this->get('/action')->assertRedirect('/tasks')->assertStatus(301);
        $this->get('/action_completed')->assertRedirect('/tasks/completed')->assertStatus(301);
        $this->get('/action/list')->assertRedirect('/tasks/list')->assertStatus(301);
        $this->get('/action/counts')->assertRedirect('/tasks/counts')->assertStatus(301);
    }

    #[Test]
    public function tasks_pages_load_for_staff(): void
    {
        $staff = new Staff();
        $staff->id = 502;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        $this->get('/tasks')->assertOk();
        $this->get('/tasks/completed')->assertOk();
    }

    #[Test]
    public function tasks_list_returns_json(): void
    {
        $staff = new Staff();
        $staff->id = 503;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        $note = new Note();
        $note->id = 777;
        $note->user_id = 503;
        $note->assigned_to = 503;
        $note->type = 'client';
        $note->is_action = 1;
        $note->status = '0';
        $note->description = 'Tasks list route test';
        $note->save();

        $response = $this->getJson('/tasks/list');

        $response->assertOk();
        $response->assertJsonPath('has_more', false);
        $this->assertArrayHasKey('html', $response->json());
        $this->assertStringContainsString('Tasks list route test', (string) $response->json('html'));
    }

    #[Test]
    public function unauthenticated_tasks_list_and_counts_return_json_401(): void
    {
        $list = $this->getJson('/tasks/list');
        $list->assertUnauthorized();
        $list->assertJsonPath('html', '');
        $list->assertJsonPath('total', 0);
        $list->assertJsonPath('has_more', false);

        $counts = $this->getJson('/tasks/counts');
        $counts->assertUnauthorized();
        $counts->assertJsonPath('personal_action', 0);
        $counts->assertJsonPath('unauthenticated', true);
    }
}
