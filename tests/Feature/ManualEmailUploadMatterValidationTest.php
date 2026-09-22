<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Http\Controllers\CRM\EmailUploadController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualEmailUploadMatterValidationTest extends TestCase
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
    public function email_upload_rejects_matter_belonging_to_different_client(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.upload@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $clientA = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'A',
            'email' => 'clientA.mail@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $clientB = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'B',
            'email' => 'clientB.mail@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $matterA = ClientMatter::create([
            'client_id' => $clientA->id,
            'client_unique_matter_no' => 'MAT-MAIL-A',
            'status' => '1',
        ]);

        $file = UploadedFile::fake()->create('email.eml', 100);

        $this->actingAs($admin, 'admin');

        $response = $this->post('/upload-fetch-mail', [
            'client_id' => $clientB->id,
            'type' => 'client',
            'upload_inbox_mail_client_matter_id' => $matterA->id,
            'email_files' => [$file],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error_code' => 'invalid_matter']);
    }

    #[Test]
    public function email_upload_returns_concrete_message_for_oversized_file(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'Upload',
            'email' => 'admin.upload.size@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $client = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'Size',
            'email' => 'client.size.mail@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        // Larger than crm.email_upload_max_kb (default 30720 KB = 30 MB).
        $file = UploadedFile::fake()->create(
            'Reminder _ Samridhi & Preet S _ SAMR2600082 _ long subject.msg',
            40 * 1024
        );

        $this->actingAs($admin, 'admin');

        $response = $this->post('/upload-fetch-mail', [
            'client_id' => $client->id,
            'type' => 'client',
            'email_files' => [$file],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'validation');
        $response->assertJsonMissing(['message' => 'Validation failed']);
        $this->assertStringContainsString('30 MB', (string) $response->json('message'));
    }

    #[Test]
    public function import_email_from_context_rejects_mismatched_matter(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin2',
            'last_name' => 'User2',
            'email' => 'admin.upload2@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $clientA = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'A2',
            'email' => 'clientA2.mail@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $clientB = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'B2',
            'email' => 'clientB2.mail@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $matterA = ClientMatter::create([
            'client_id' => $clientA->id,
            'client_unique_matter_no' => 'MAT-MAIL-A2',
            'status' => '1',
        ]);

        $file = UploadedFile::fake()->create('email.msg', 100);

        $this->actingAs($admin, 'admin');

        $controller = app(EmailUploadController::class);
        $result = $controller->importEmailFromContext($file, $clientB->id, 'inbox', $matterA->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_matter', $result['error_code']);
    }
}
