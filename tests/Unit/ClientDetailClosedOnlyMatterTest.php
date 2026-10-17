<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Services\ClientDetailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientDetailClosedOnlyMatterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function strip_closed_matter_ref_when_client_has_no_active_matters(): void
    {
        $client = Admin::factory()->create(['type' => 'client']);

        ClientMatter::query()->create([
            'client_id' => $client->id,
            'matter_status' => 0,
            'client_unique_matter_no' => 'CIV_1',
            'sel_matter_id' => 1,
        ]);

        $service = app(ClientDetailService::class);

        $this->assertNull($service->stripClosedOnlyMatterRef((int) $client->id, 'CIV_1'));
        $this->assertFalse($service->clientHasActiveMatters((int) $client->id));
    }

    #[Test]
    public function detail_redirects_away_from_closed_only_matter_ref_in_url(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $staff = Staff::factory()->create(['role' => 1]);
        $client = Admin::factory()->create(['type' => 'client']);

        ClientMatter::query()->create([
            'client_id' => $client->id,
            'matter_status' => 0,
            'client_unique_matter_no' => 'CIV_1',
            'sel_matter_id' => 1,
        ]);

        $encodeId = base64_encode(convert_uuencode((string) $client->id));

        $this->actingAs($staff, 'admin');

        $response = $this->get('/clients/detail/' . $encodeId . '/CIV_1/personaldetails');

        $response->assertRedirect();
        $this->assertStringNotContainsString('CIV_1', (string) $response->headers->get('Location'));
    }
}
