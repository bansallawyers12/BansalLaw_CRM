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
    public function closed_matter_ref_keeps_closed_view_when_no_active_matters(): void
    {
        $client = Admin::factory()->create(['type' => 'client']);

        ClientMatter::query()->create([
            'client_id' => $client->id,
            'matter_status' => 0,
            'client_unique_matter_no' => 'CIV_1',
            'sel_matter_id' => 1,
        ]);

        $service = app(ClientDetailService::class);

        $this->assertFalse($service->clientHasActiveMatters((int) $client->id));

        $payload = $service->buildViewPayload(
            (int) $client->id,
            'CIV_1',
            'personaldetails',
            $client
        );

        $this->assertTrue($payload['isClosedMatterView']);
        $this->assertSame('CIV_1', $payload['selectedClientMatter']?->client_unique_matter_no);
    }

    #[Test]
    public function client_scope_url_is_not_closed_matter_view(): void
    {
        $client = Admin::factory()->create(['type' => 'client', 'client_id' => 'AIRI2600184']);

        ClientMatter::query()->create([
            'client_id' => $client->id,
            'matter_status' => 0,
            'client_unique_matter_no' => 'CIV_1',
            'sel_matter_id' => 1,
        ]);

        $payload = app(ClientDetailService::class)->buildViewPayload(
            (int) $client->id,
            null,
            'personaldetails',
            $client
        );

        $this->assertFalse($payload['isClosedMatterView']);
        $this->assertNull($payload['selectedClientMatter']);
    }

    #[Test]
    public function client_scope_detail_shows_record_and_closed_matter_rows(): void
    {
        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $staff = Staff::factory()->create(['role' => 1]);
        $client = Admin::factory()->create(['type' => 'client', 'client_id' => 'AIRI2600184']);

        ClientMatter::query()->create([
            'client_id' => $client->id,
            'matter_status' => 0,
            'client_unique_matter_no' => 'CIV_1',
            'sel_matter_id' => 1,
        ]);

        $encodeId = base64_encode(convert_uuencode((string) $client->id));

        $this->actingAs($staff, 'admin');

        $response = $this->get('/clients/detail/' . $encodeId . '/personaldetails');

        $response->assertOk();
        $response->assertSee('AIRI2600184', false);
        $response->assertSee('CIV_1', false);
        $response->assertSee('Add new matter', false);
        $response->assertDontSee('This matter is closed', false);
    }

    #[Test]
    public function closed_matter_url_shows_read_only_banner(): void
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

        $response->assertOk();
        $response->assertSee('This matter is closed', false);
        $response->assertSee('Add', false);
    }
}
