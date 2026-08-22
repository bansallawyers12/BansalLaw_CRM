<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskRouteAliasesTest extends TestCase
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
    public function deprecated_action_route_names_still_resolve(): void
    {
        $this->assertSame(url('/action'), route('assignee.action'));
        $this->assertSame(url('/action_completed'), route('assignee.action_completed'));
        $this->assertSame(url('/action/list'), route('action.list'));
        $this->assertSame(url('/action/counts'), route('action.counts'));
        $this->assertSame(url('/dashboard/update-action-completed'), route('dashboard.update-action-completed'));
    }

    #[Test]
    public function tasks_page_and_legacy_action_page_both_load_for_staff(): void
    {
        $staff = new Staff();
        $staff->id = 501;
        $staff->role = 1;
        $staff->first_name = 'Task';
        $staff->last_name = 'Tester';

        $this->actingAs($staff, 'admin');

        $this->get('/tasks')->assertOk();
        $this->get('/action')->assertOk();
        $this->get('/tasks/completed')->assertOk();
        $this->get('/action_completed')->assertOk();
    }

    #[Test]
    public function tasks_list_and_legacy_action_list_both_return_json(): void
    {
        $staff = new Staff();
        $staff->id = 502;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        $note = new Note();
        $note->id = 777;
        $note->user_id = 502;
        $note->assigned_to = 502;
        $note->type = 'client';
        $note->is_action = 1;
        $note->status = '0';
        $note->description = 'Canonical route test task';
        $note->save();

        $canonical = $this->getJson('/tasks/list');
        $legacy = $this->getJson('/action/list');

        $canonical->assertOk();
        $legacy->assertOk();

        $canonicalBody = json_encode($canonical->json());
        $legacyBody = json_encode($legacy->json());
        $this->assertStringContainsString('Canonical route test task', $canonicalBody);
        $this->assertStringContainsString('Canonical route test task', $legacyBody);
    }
}
