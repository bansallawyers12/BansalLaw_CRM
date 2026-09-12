<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsBulkRouteRemovedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function bulk_sms_stub_route_is_removed(): void
    {
        $staff = new Staff();
        $staff->id = 910;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        $this->postJson('/adminconsole/features/sms/send/bulk', [
            'message' => 'Hello',
            'recipients' => [['phone' => '+61400111222']],
        ])->assertNotFound();
    }
}
