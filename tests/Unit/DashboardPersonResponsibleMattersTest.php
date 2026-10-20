<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPersonResponsibleMattersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_only_open_matters_where_the_user_is_person_responsible(): void
    {
        if (! Schema::hasTable('client_matters') || ! Schema::hasColumn('client_matters', 'sel_person_responsible')) {
            $this->markTestSkipped('client_matters.sel_person_responsible is not available');
        }

        $owner = Staff::factory()->create();
        $other = Staff::factory()->create();
        $client = Admin::factory()->create([
            'type' => 'client',
            'is_archived' => 0,
            'first_name' => 'Sunshine',
            'last_name' => 'College',
        ]);

        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CIV_6',
            'sel_person_responsible' => $owner->id,
        ]);
        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CIV_9',
            'sel_person_responsible' => $other->id,
        ]);
        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 0,
            'client_unique_matter_no' => 'CIV_1',
            'sel_person_responsible' => $owner->id,
        ]);

        $matters = app(DashboardService::class)->getPersonResponsibleMatters($owner);

        $this->assertCount(1, $matters);
        $this->assertSame('CIV_6', $matters->first()->client_unique_matter_no);

        $html = view('components.dashboard.responsible-matters', ['matters' => $matters])->render();
        $this->assertStringContainsString('CIV_6', $html);
        $this->assertStringContainsString('/CIV_6', $html);
        $this->assertStringNotContainsString('CIV_9', $html);

        $nobody = Staff::factory()->create();
        $emptyHtml = view('components.dashboard.responsible-matters', [
            'matters' => app(DashboardService::class)->getPersonResponsibleMatters($nobody),
        ])->render();
        $this->assertSame('', trim($emptyHtml));
        $this->assertStringNotContainsString('responsible-matters', $emptyHtml);
    }
}
