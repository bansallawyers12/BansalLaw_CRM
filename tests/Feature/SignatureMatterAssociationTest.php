<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\Staff;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SignatureMatterAssociationTest extends TestCase
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
    public function signature_service_rejects_matter_from_another_client(): void
    {
        $clientA = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'A',
            'email' => 'clientA@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $clientB = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'B',
            'email' => 'clientB@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $matterA = ClientMatter::create([
            'client_id' => $clientA->id,
            'client_unique_matter_no' => 'MAT-A-001',
            'status' => '1',
        ]);

        $document = Document::create([
            'file_name' => 'test.pdf',
            'filetype' => 'pdf',
            'status' => 'draft',
            'client_id' => $clientB->id,
        ]);

        $service = app(SignatureService::class);
        $result = $service->associateWithCategory($document, 'client', $clientB->id, $matterA->id, 'matter');

        $this->assertFalse($result);
        $this->assertNull($document->fresh()->client_matter_id);
    }

    #[Test]
    public function signature_service_accepts_matter_from_matching_client(): void
    {
        $clientA = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'A2',
            'email' => 'clientA2@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $matterA = ClientMatter::create([
            'client_id' => $clientA->id,
            'client_unique_matter_no' => 'MAT-A-002',
            'status' => '1',
        ]);

        $document = Document::create([
            'file_name' => 'test2.pdf',
            'filetype' => 'pdf',
            'status' => 'draft',
            'client_id' => $clientA->id,
        ]);

        $service = app(SignatureService::class);
        $result = $service->associateWithCategory($document, 'client', $clientA->id, $matterA->id, 'matter');

        $this->assertTrue($result);
        $this->assertEquals($matterA->id, $document->fresh()->client_matter_id);
    }

    #[Test]
    public function signature_dashboard_controller_rejects_mismatched_matter(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.sig@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $clientA = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'A3',
            'email' => 'clientA3@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $clientB = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'B3',
            'email' => 'clientB3@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $matterA = ClientMatter::create([
            'client_id' => $clientA->id,
            'client_unique_matter_no' => 'MAT-A-003',
            'status' => '1',
        ]);

        $document = Document::create([
            'file_name' => 'test3.pdf',
            'filetype' => 'pdf',
            'status' => 'draft',
            'client_id' => $clientB->id,
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->from('/signatures')->post(route('signatures.associate', $document->id), [
            'entity_type' => 'client',
            'entity_id' => $clientB->id,
            'matter_id' => $matterA->id,
            'doc_category' => 'visa',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull($document->fresh()->client_matter_id);
    }
}
