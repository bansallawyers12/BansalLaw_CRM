<?php

namespace Tests\Unit;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\Staff;
use App\Support\ClientActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientActivityTest extends TestCase
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
    public function log_writes_timeline_row_for_staff_and_client(): void
    {
        $staff = Staff::create([
            'first_name' => 'Pat',
            'last_name' => 'Admin',
            'email' => 'client.activity@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Ada',
            'last_name' => 'Client',
            'email' => 'ada.client@example.com',
            'type' => 'client',
            'status' => 1,
            'password' => bcrypt('password'),
        ]);

        $log = ClientActivity::log(
            (int) $client->id,
            'uploaded document',
            ClientActivity::TYPE_DOCUMENT,
            '<p>Passport.pdf</p>'
        );

        $this->assertInstanceOf(ActivitiesLog::class, $log);
        $this->assertDatabaseHas('activities_logs', [
            'id' => $log->id,
            'client_id' => $client->id,
            'created_by' => $staff->id,
            'subject' => 'uploaded document',
            'activity_type' => 'document',
        ]);
    }

    #[Test]
    public function log_skips_invalid_client(): void
    {
        $this->assertNull(ClientActivity::log(0, 'noop'));
    }
}
