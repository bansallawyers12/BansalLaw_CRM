<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Support\BookingCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaraLeftoverCleanupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function staff_form_partial_uses_practising_certificate_not_marn(): void
    {
        $admin = Staff::factory()->superAdmin()->create([
            'email' => 'form-admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'admin');
        $html = View::make('AdminConsole.staff.partials.form-fields', [
            'mode' => 'create',
            'fetchedData' => null,
            'fieldPrefix' => 'create_staff',
            'usertype' => collect([(object) ['id' => 2, 'name' => 'Staff']]),
        ])->render();

        $this->assertStringContainsString('legal_practitioner_number', $html);
        $this->assertStringContainsString('Practising certificate', $html);
        $this->assertStringNotContainsString('marn_number', $html);
        $this->assertStringNotContainsString('MARN', $html);
    }

    #[Test]
    public function staff_update_saves_legal_practitioner_number_not_marn(): void
    {
        $admin = Staff::factory()->superAdmin()->create([
            'email' => 'admin-mara@test.com',
            'password' => bcrypt('password'),
        ]);

        $staff = Staff::factory()->regularStaff()->create([
            'email' => 'solicitor-mara@test.com',
            'is_solicitor' => 0,
            'legal_practitioner_number' => null,
        ]);

        $response = $this->actingAs($admin, 'admin')->put(
            route('adminconsole.staff.update', $staff->id),
            [
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'email' => $staff->email,
                'phone' => $staff->phone ?: '0400000000',
                'role' => $staff->role,
                'is_solicitor' => '1',
                'legal_practitioner_number' => 'LPN-998877',
                'marn_number' => 'MARN-should-not-save',
            ]
        );

        $response->assertRedirect();

        $staff->refresh();
        $this->assertSame(1, (int) $staff->is_solicitor);
        $this->assertSame('LPN-998877', $staff->legal_practitioner_number);
        $this->assertFalse(Schema::hasColumn('staff', 'marn_number'));
        $this->assertArrayNotHasKey('marn_number', $staff->getAttributes());
    }

    #[Test]
    public function firm_email_domains_exclude_immigration_brand(): void
    {
        $domains = config('app.brand.firm_email_domains', []);
        $this->assertIsArray($domains);
        $this->assertNotEmpty($domains);
        $this->assertContains('@bansallawyers.com.au', $domains);

        foreach ($domains as $domain) {
            $this->assertStringNotContainsString('immigration', strtolower((string) $domain));
            $this->assertStringNotContainsString('bansalmigration', strtolower((string) $domain));
        }
    }

    #[Test]
    public function public_service_catalogue_does_not_advertise_migration_advice(): void
    {
        foreach (BookingCatalogue::publicServiceTypeList() as $product) {
            $blob = strtolower(($product['name'] ?? '') . ' ' . ($product['description'] ?? ''));
            $this->assertStringNotContainsString('migration advice', $blob);
            $this->assertStringNotContainsString('comprehensive migration', $blob);
            $this->assertStringNotContainsString('visa pathway', $blob);
        }

        $noeLabels = array_column(BookingCatalogue::crmNatureOfEnquiry(), 'label');
        $this->assertNotContains('Migration Advice', $noeLabels);
        $this->assertNotContains('Migration Consultation', $noeLabels);
        $this->assertContains('Immigration Law', $noeLabels);
    }

    #[Test]
    public function appointment_and_signature_emails_use_law_firm_copy(): void
    {
        $confirmation = View::make('emails.appointment-confirmation', [
            'clientName' => 'Alex Client',
            'appointmentDate' => '15 August 2026',
            'appointmentTime' => '10:00 AM',
            'serviceType' => 'Legal Consultation',
            'locationAddress' => 'Level 8/278 Collins St Melbourne VIC 3000',
            'locationPhone' => '03 9000 0000',
            'consultant' => 'Ajay',
            'adminNotes' => null,
        ])->render();

        $this->assertStringNotContainsString('Immigration Consultation', $confirmation);
        $this->assertStringNotContainsString('immigration authorities', strtolower($confirmation));
        $this->assertStringNotContainsString('visa', strtolower($confirmation));

        $reminder = View::make('emails.signature.reminder', [
            'signerName' => 'Alex Client',
            'documentTitle' => 'Costs Disclosure',
            'signingUrl' => 'https://example.com/sign/1',
            'reminderNumber' => 1,
            'dueDate' => '20 August 2026',
        ])->render();

        $this->assertStringContainsString('progress of your matter', $reminder);
        $this->assertStringNotContainsString('visa application', strtolower($reminder));
    }

    #[Test]
    public function agreement_template_has_no_legacy_mara_merge_fields(): void
    {
        $path = storage_path('app/templates/agreement_template.docx');
        if (! is_file($path)) {
            $this->markTestSkipped('agreement_template.docx is not present in this environment');
        }

        $vars = (new TemplateProcessor($path))->getVariables();
        foreach ($vars as $var) {
            $this->assertDoesNotMatchRegularExpression(
                '/^(MARN|AgentName|AgentSurName|AgentTitle|TotalAgentFee|TotalDoHA|DoHA)/i',
                $var,
                "Legacy merge field still present: {$var}"
            );
        }

        $this->assertContains('LegalPractitionerNumber', $vars);
        $this->assertContains('SolicitorName', $vars);
        $this->assertContains('TotalDisbursements', $vars);
    }
}
