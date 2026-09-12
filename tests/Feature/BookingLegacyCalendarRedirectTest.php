<?php

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingLegacyCalendarRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function legacy_calendar_type_urls_redirect_to_ajay(): void
    {
        $staff = new Staff();
        $staff->id = 601;
        $staff->role = 1;

        $this->actingAs($staff, 'admin');

        foreach (['paid', 'jrp', 'education', 'tourist', 'adelaide'] as $legacyType) {
            $this->get('/booking/calendar/'.$legacyType)
                ->assertRedirect('/booking/calendar/ajay')
                ->assertStatus(301);
        }
    }
}
