<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreLeadWafJsonTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function waf_json_route_is_hidden_when_feature_disabled(): void
    {
        config([
            'crm.temporary_waf_safe_json_post' => false,
            'crm.temporary_waf_safe_lead_store' => false,
        ]);
        $staff = Staff::factory()->create(['status' => 1]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('leads.store.waf_json'), ['payload' => ['first_name' => 'A']])
            ->assertNotFound();
    }

    #[Test]
    public function waf_json_route_requires_json_body_when_enabled(): void
    {
        config([
            'crm.temporary_waf_safe_json_post' => true,
            'crm.temporary_waf_safe_lead_store' => true,
        ]);
        $staff = Staff::factory()->create(['status' => 1]);

        $this->actingAs($staff, 'admin')
            ->post(route('leads.store.waf_json'), ['first_name' => 'A'])
            ->assertStatus(415);
    }

    #[Test]
    public function waf_json_route_rejects_empty_payload_when_enabled(): void
    {
        config([
            'crm.temporary_waf_safe_json_post' => true,
            'crm.temporary_waf_safe_lead_store' => true,
        ]);
        $staff = Staff::factory()->create(['status' => 1]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('leads.store.waf_json'), [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
