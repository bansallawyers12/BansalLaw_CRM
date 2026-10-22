<?php

namespace Tests\Unit;

use App\Models\CheckinLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfficeVisitViewerScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function in_person_list_is_limited_to_the_assignee(): void
    {
        config([
            'crm_access.matter_scope_bypass_role_ids' => [1],
            'crm_access.quick_access_only_role_ids' => [14],
        ]);

        $assignee = Staff::factory()->create([
            'status' => 1,
            'role' => 2,
            'first_name' => 'Amer',
            'last_name' => 'Zaib',
            'email' => 'amer.zaib.visits@example.test',
        ]);
        $other = Staff::factory()->create([
            'status' => 1,
            'role' => 2,
            'first_name' => 'Priya',
            'last_name' => 'Sharma',
            'email' => 'priya.sharma.visits@example.test',
        ]);
        $super = Staff::factory()->superAdmin()->create([
            'status' => 1,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'super.visits@example.test',
        ]);

        $own = CheckinLog::query()->create([
            'user_id' => $assignee->id,
            'contact_type' => 'Lead',
            'status' => 1,
            'visit_purpose' => 'General Inquiry',
        ]);
        $theirs = CheckinLog::query()->create([
            'user_id' => $other->id,
            'contact_type' => 'Lead',
            'status' => 1,
            'visit_purpose' => 'Appointment Follow-up',
        ]);

        $visibleToAssignee = CheckinLog::query()->forOfficeVisitViewer($assignee)->pluck('id')->all();
        $visibleToSuper = CheckinLog::query()->forOfficeVisitViewer($super)->pluck('id')->all();

        $this->assertSame([(int) $own->id], array_map('intval', $visibleToAssignee));
        $this->assertContains((int) $own->id, array_map('intval', $visibleToSuper));
        $this->assertContains((int) $theirs->id, array_map('intval', $visibleToSuper));
        $this->assertFalse(CheckinLog::visibleToViewer($theirs, $assignee));
        $this->assertTrue(CheckinLog::visibleToViewer($theirs, $super));
    }
}
