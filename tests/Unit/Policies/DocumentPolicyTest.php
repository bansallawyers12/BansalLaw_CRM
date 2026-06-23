<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Staff;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Signer;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class DocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DocumentPolicy();
    }

    #[Test]
    public function admin_can_view_any_document()
    {
        $admin = Admin::factory()->create(['role' => 1]); // Super admin
        $document = Document::factory()->create();

        $this->assertTrue($this->policy->view($admin, $document));
    }

    #[Test]
    public function creator_can_view_their_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($this->policy->view($user, $document));
    }

    #[Test]
    public function signer_can_view_document_they_need_to_sign()
    {
        $user = Admin::factory()->create(['role' => 2, 'email' => 'signer@example.com']);
        $document = Document::factory()->create();
        
        Signer::factory()->create([
            'document_id' => $document->id,
            'email' => $user->email
        ]);

        $this->assertTrue($this->policy->view($user, $document));
    }

    #[Test]
    public function user_can_view_document_associated_with_their_client()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'client_id' => $user->id,
            'lead_id' => null
        ]);

        $this->assertTrue($this->policy->view($user, $document));
    }

    #[Test]
    public function user_can_view_document_associated_with_their_lead()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $lead = Lead::factory()->create(['user_id' => $user->id]);
        $document = Document::factory()->create([
            'lead_id' => $lead->id,
            'client_id' => null
        ]);

        $this->assertTrue($this->policy->view($user, $document));
    }

    #[Test]
    public function any_user_can_view_any_document()
    {
        // Policy grants global view access to all authenticated users
        $user = Admin::factory()->create();
        $otherUser = Admin::factory()->create();
        $document = Document::factory()->create([
            'created_by' => Staff::factory()->create()->id,
            'client_id' => null,
            'lead_id' => null,
        ]);

        $this->assertTrue($this->policy->view($user, $document));
        $this->assertTrue($this->policy->view($otherUser, $document));
    }

    #[Test]
    public function admin_can_update_any_document()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $document = Document::factory()->create();

        $this->assertTrue($this->policy->update($admin, $document));
    }

    #[Test]
    public function creator_can_update_their_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($this->policy->update($user, $document));
    }

    #[Test]
    public function any_user_can_update_any_document()
    {
        // Policy grants global update access
        $user = Admin::factory()->create();
        $staff = Staff::factory()->create();
        $document = Document::factory()->create(['created_by' => $staff->id]);

        $this->assertTrue($this->policy->update($user, $document));
    }

    #[Test]
    public function admin_can_delete_any_document()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $document = Document::factory()->create(['status' => 'draft']);

        $this->assertTrue($this->policy->delete($admin, $document));
    }

    #[Test]
    public function creator_can_delete_their_draft_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'created_by' => $user->id,
            'status' => 'draft'
        ]);

        $this->assertTrue($this->policy->delete($user, $document));
    }

    #[Test]
    public function creator_cannot_delete_their_signed_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'created_by' => $user->id,
            'status' => 'signed'
        ]);

        $this->assertFalse($this->policy->delete($user, $document));
    }

    #[Test]
    public function any_user_can_delete_a_non_signed_document()
    {
        // Policy allows deletion of non-signed documents by anyone
        $user = Admin::factory()->create();
        $staff = Staff::factory()->create();
        $document = Document::factory()->create([
            'created_by' => $staff->id,
            'status'     => 'draft',
        ]);

        $this->assertTrue($this->policy->delete($user, $document));
    }

    #[Test]
    public function view_any_is_always_true_for_authenticated_users()
    {
        // Policy grants global viewAny access
        $staff = Staff::factory()->create(['role' => 1]);
        $regularStaff = Staff::factory()->create(['role' => 2]);

        $this->assertTrue($this->policy->viewAny($staff));
        $this->assertTrue($this->policy->viewAny($regularStaff));
    }

    #[Test]
    public function only_staff_model_instances_can_create_documents()
    {
        // Policy create() checks instanceof Staff
        $staff = Staff::factory()->create(['role' => 1]);
        $regularStaff = Staff::factory()->create(['role' => 2]);
        $client = Admin::factory()->create(); // Client portal user (Admin model)

        $this->assertTrue($this->policy->create($staff));
        $this->assertTrue($this->policy->create($regularStaff));
        $this->assertFalse($this->policy->create($client));
    }

    #[Test]
    public function admin_can_send_reminder_for_any_document()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $document = Document::factory()->create();

        $this->assertTrue($this->policy->sendReminder($admin, $document));
    }

    #[Test]
    public function creator_can_send_reminder_for_their_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($this->policy->sendReminder($user, $document));
    }

    #[Test]
    public function any_user_can_send_reminder_for_any_document()
    {
        // Policy sendReminder() grants global access
        $user = Admin::factory()->create();
        $staff = Staff::factory()->create();
        $document = Document::factory()->create(['created_by' => $staff->id]);

        $this->assertTrue($this->policy->sendReminder($user, $document));
    }
}

