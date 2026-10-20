<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\EmailLog;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientOutlookEmailFetchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function client_email_list_without_matter_id_returns_mail_for_all_matters(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);

        $matterA = ClientMatter::query()->create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'FAM_1',
            'status' => '1',
        ]);
        $matterB = ClientMatter::query()->create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'CIV_2',
            'status' => '1',
        ]);

        EmailLog::query()->create([
            'from_mail' => 'a@example.com',
            'to_mail' => 'b@example.com',
            'subject' => 'Matter A mail',
            'client_id' => $client->id,
            'client_matter_id' => $matterA->id,
            'type' => 'client',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'conversion_type' => 'conversion_email_fetch',
        ]);
        EmailLog::query()->create([
            'from_mail' => 'c@example.com',
            'to_mail' => 'd@example.com',
            'subject' => 'Matter B mail',
            'client_id' => $client->id,
            'client_matter_id' => $matterB->id,
            'type' => 'client',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'conversion_type' => 'conversion_email_fetch',
        ]);

        $this->actingAs($staff, 'admin');

        $response = $this->getJson('/clients/outlook/fetch-all?folder=all&client_id=' . $client->id);

        $response->assertOk();
        $subjects = collect($response->json('emails') ?? [])->pluck('subject')->all();
        $this->assertContains('Matter A mail', $subjects);
        $this->assertContains('Matter B mail', $subjects);
    }

    #[Test]
    public function client_email_list_with_matter_id_includes_client_scoped_uploads(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);

        $matter = ClientMatter::query()->create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'FAM_1',
            'status' => '1',
        ]);

        EmailLog::query()->create([
            'from_mail' => 'a@example.com',
            'to_mail' => 'b@example.com',
            'subject' => 'Client scoped upload',
            'client_id' => $client->id,
            'client_matter_id' => null,
            'type' => '',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'conversion_type' => 'conversion_email_fetch',
            'uploaded_doc_id' => 999,
        ]);

        $this->actingAs($staff, 'admin');

        $response = $this->getJson(
            '/clients/outlook/fetch-all?folder=all&client_id=' . $client->id . '&client_matter_id=' . $matter->id
        );

        $response->assertOk();
        $subjects = collect($response->json('emails') ?? [])->pluck('subject')->all();
        $this->assertContains('Client scoped upload', $subjects);
    }
}
