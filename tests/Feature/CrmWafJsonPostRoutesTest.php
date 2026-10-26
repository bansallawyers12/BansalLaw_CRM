<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrmWafJsonPostRoutesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function note_waf_json_route_is_hidden_when_feature_disabled(): void
    {
        config(['crm.temporary_waf_safe_json_post' => false]);
        $staff = Staff::factory()->create(['status' => 1]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('clients.createnote.waf_json'), ['payload' => ['title' => 'x']])
            ->assertNotFound();
    }

    #[Test]
    public function matter_task_waf_json_route_rejects_empty_payload_when_enabled(): void
    {
        config(['crm.temporary_waf_safe_json_post' => true]);
        $staff = Staff::factory()->create(['status' => 1]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('clients.matterTask.store.waf_json'), [])
            ->assertStatus(422);
    }

    #[Test]
    public function booking_update_datetime_waf_json_route_is_hidden_when_feature_disabled(): void
    {
        config(['crm.temporary_waf_safe_json_post' => false]);
        $staff = Staff::factory()->create(['status' => 1]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('booking.appointments.update-datetime.waf_json', ['id' => 1]), [
                'payload' => ['appointment_date' => '2026-10-23', 'appointment_time' => '12:30'],
            ])
            ->assertNotFound();
    }
}
