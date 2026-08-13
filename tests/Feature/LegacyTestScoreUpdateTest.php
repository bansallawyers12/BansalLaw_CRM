<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientTestScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyTestScoreUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function unauthenticated_user_cannot_update_test_scores(): void
    {
        $client = Admin::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $response = $this->post('/edit-test-scores', [
            'client_id' => $client->id,
            'band_score_1_1' => '7.5',
            'score_1' => '7.5',
        ]);

        // Unauthenticated access should fail with unauthorized or redirect to login
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 401 || $response->status() === 403
        );
    }

    #[Test]
    public function authenticated_admin_can_update_test_scores_without_null_dereference(): void
    {
        $staff = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'type' => 'staff',
            'role' => 1, // Admin role
        ]);

        $client = Admin::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $this->actingAs($staff, 'admin');

        try {
            $response = $this->post('/edit-test-scores', [
                'client_id' => $client->id,
                'band_score_1_1' => '7.5',
                'score_1' => '7.5',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->fail("QueryException: " . $e->getMessage());
        }

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('client_testscore', [
            'client_id' => $client->id,
            'admin_id' => $staff->id,
            'test_type' => 'TOEFL',
            'listening' => '7.5',
            'overall_score' => '7.5',
        ]);
    }

    #[Test]
    public function restricted_staff_cannot_update_unassigned_client_test_scores(): void
    {
        $staff = Admin::create([
            'first_name' => 'Restricted',
            'last_name' => 'Staff',
            'email' => 'restricted@example.com',
            'password' => bcrypt('password'),
            'type' => 'staff',
            'role' => 2, // Regular staff role subject to visibility
        ]);

        $client = Admin::create([
            'first_name' => 'Unassigned',
            'last_name' => 'Client',
            'email' => 'unassigned@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
            'assignee_id' => 99999, // Assigned to another staff member
        ]);

        $this->actingAs($staff, 'admin');

        $response = $this->post('/edit-test-scores', [
            'client_id' => $client->id,
            'band_score_5_2' => '8.0',
            'score_2' => '8.0',
        ]);

        $response->assertSessionHasErrors('error');
    }

    #[Test]
    public function authenticated_admin_can_update_ielts_and_pte_scores(): void
    {
        $staff = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User2',
            'email' => 'admin2@example.com',
            'password' => bcrypt('password'),
            'type' => 'staff',
            'role' => 1,
        ]);

        $client = Admin::create([
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob.jones@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $this->actingAs($staff, 'admin');

        $response = $this->post('/edit-test-scores', [
            'client_id' => $client->id,
            'band_score_5_2' => '8.0',
            'band_score_6_2' => '8.5',
            'band_score_7_2' => '7.5',
            'band_score_8_2' => '8.0',
            'score_2' => '8.0',
            'band_score_9_3' => '79',
            'band_score_10_3' => '85',
            'band_score_11_3' => '79',
            'band_score_12_3' => '80',
            'score_3' => '81',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('client_testscore', [
            'client_id' => $client->id,
            'admin_id' => $staff->id,
            'test_type' => 'IELTS',
            'listening' => '8.0',
            'reading' => '8.5',
            'writing' => '7.5',
            'speaking' => '8.0',
            'overall_score' => '8.0',
        ]);

        $this->assertDatabaseHas('client_testscore', [
            'client_id' => $client->id,
            'admin_id' => $staff->id,
            'test_type' => 'PTE',
            'listening' => '79',
            'reading' => '85',
            'writing' => '79',
            'speaking' => '80',
            'overall_score' => '81',
        ]);
    }
}
