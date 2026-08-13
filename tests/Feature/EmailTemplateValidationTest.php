<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTemplateValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function model_throws_exception_if_name_is_empty()
    {
        $this->expectException(\InvalidArgumentException::class);

        EmailTemplate::create([
            'type' => EmailTemplate::TYPE_CRM,
            'name' => '   ',
            'subject' => 'Valid Subject',
            'description' => 'Valid Content',
        ]);
    }

    #[Test]
    public function model_throws_exception_if_subject_is_empty()
    {
        $this->expectException(\InvalidArgumentException::class);

        EmailTemplate::create([
            'type' => EmailTemplate::TYPE_CRM,
            'name' => 'Valid Name',
            'subject' => '',
            'description' => 'Valid Content',
        ]);
    }

    #[Test]
    public function model_throws_exception_if_description_is_empty()
    {
        $this->expectException(\InvalidArgumentException::class);

        EmailTemplate::create([
            'type' => EmailTemplate::TYPE_CRM,
            'name' => 'Valid Name',
            'subject' => 'Valid Subject',
            'description' => '  ',
        ]);
    }

    #[Test]
    public function saving_valid_email_template_succeeds()
    {
        $template = EmailTemplate::create([
            'type' => EmailTemplate::TYPE_CRM,
            'name' => 'Welcome Template',
            'subject' => 'Welcome to Bansal Lawyers',
            'description' => 'Dear {name}, Welcome!',
        ]);

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'name' => 'Welcome Template',
            'subject' => 'Welcome to Bansal Lawyers',
        ]);
    }

    #[Test]
    public function crm_email_template_store_requires_subject_and_description()
    {
        $role = \App\Models\Userrole::firstOrCreate(['id' => 1], ['role' => 'Super Admin', 'name' => 'Super Admin']);

        $staff = \App\Models\Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin_tpl_test@bansallawyers.com.au',
            'password' => bcrypt('password'),
            'role' => $role->id,
            'status' => 1,
            'is_grant_super_admin' => 1,
        ]);
        $this->actingAs($staff, 'admin');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post(route('adminconsole.features.crmemailtemplate.store'), [
            'name' => 'Incomplete Template',
            'subject' => '',
            'description' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject', 'description']);
    }
}
