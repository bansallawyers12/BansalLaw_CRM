<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\ClientExportService;
use App\Services\ClientImportService;
use App\Models\Admin;
use App\Models\Matter;
use App\Models\ClientVisaCountry;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Verifies visa type and visa expiry export/import fix.
 * - Export includes visa_type_matter_title, visa_type_matter_nick_name; dates as Y-m-d.
 * - Import resolves visa_type by nick_name/title; syncs last visa to client.visa_type / visaExpiry.
 *
 * Requires matters, client_visa_countries, and admins tables. Skips when schema is incomplete
 * (e.g. test DB without full migrations).
 */
class VisaExportImportTest extends TestCase
{
    use DatabaseTransactions;

    protected ?Admin $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('matters') || ! Schema::hasTable('client_visa_countries')) {
            $this->markTestSkipped('Visa export/import tests require matters and client_visa_countries tables.');
        }
        $this->admin = Admin::create([
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'test-admin-visa-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 1,
        ]);
        $this->actingAs($this->admin, 'admin');
    }

    /** @test */
    public function export_includes_portable_visa_type_and_normalised_dates(): void
    {
        $matter = Matter::create([
            'title' => '600 - Visitor - Outside Australia (Outside Australia)',
            'nick_name' => '600',
        ]);

        $client = Admin::create([
            'first_name' => 'Export',
            'last_name' => 'VisaTest',
            'email' => 'export-visa-test-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'type' => 'client',
            'client_id' => 'EXPT' . time(),
            'client_counter' => str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        ]);

        $client->country_passport = 'India';
        $client->save();

        ClientVisaCountry::create([
            'client_id' => $client->id,
            'admin_id' => $this->admin->id ?? 1,
            'visa_type' => $matter->id,
            'visa_expiry_date' => '2025-12-31',
            'visa_grant_date' => '2022-06-03',
            'visa_description' => null,
        ]);

        $export = (new ClientExportService())->exportClient($client->id);

        $this->assertArrayHasKey('visa_countries', $export);
        $this->assertIsArray($export['visa_countries']);
        $this->assertCount(1, $export['visa_countries']);

        $v = $export['visa_countries'][0];
        $this->assertArrayHasKey('visa_type', $v);
        $this->assertArrayHasKey('visa_type_matter_title', $v);
        $this->assertArrayHasKey('visa_type_matter_nick_name', $v);
        $this->assertArrayHasKey('visa_expiry_date', $v);
        $this->assertArrayHasKey('visa_grant_date', $v);

        $this->assertSame($matter->id, $v['visa_type']);
        $this->assertSame('600 - Visitor - Outside Australia (Outside Australia)', $v['visa_type_matter_title']);
        $this->assertSame('600', $v['visa_type_matter_nick_name']);
        $this->assertSame('2025-12-31', $v['visa_expiry_date']);
        $this->assertSame('2022-06-03', $v['visa_grant_date']);
    }

    /** @test */
    public function import_resolves_visa_type_by_nick_name_and_syncs_client_visa_summary(): void
    {
        $matter = Matter::create([
            'title' => '600 - Visitor - Outside Australia (Outside Australia)',
            'nick_name' => '600',
        ]);

        $importData = [
            'version' => '1.0',
            'client' => [
                'first_name' => 'Import',
                'last_name' => 'VisaTest',
                'email' => 'import-visa-test-' . uniqid() . '@example.com',
            ],
            'visa_countries' => [
                [
                    'visa_type' => 999,
                    'visa_type_matter_title' => '600 - Visitor - Outside Australia (Outside Australia)',
                    'visa_type_matter_nick_name' => '600',
                    'visa_description' => null,
                    'visa_expiry_date' => '2025-12-31',
                    'visa_grant_date' => '2022-06-03',
                ],
            ],
        ];

        $importService = app(ClientImportService::class);
        $result = $importService->importClient($importData, true);

        $this->assertTrue($result['success'], $result['message'] ?? 'Import failed');
        $this->assertNotNull($result['client_id']);

        $imported = ClientVisaCountry::where('client_id', $result['client_id'])->get();
        $this->assertCount(1, $imported);

        $visa = $imported->first();
        $this->assertSame($matter->id, (int) $visa->visa_type);
        $this->assertSame('2025-12-31', $visa->visa_expiry_date);
        $this->assertSame('2022-06-03', $visa->visa_grant_date);

        $client = Admin::find($result['client_id']);
        if (Schema::hasColumn('admins', 'visa_type') && Schema::hasColumn('admins', 'visaExpiry')) {
            $this->assertSame((string) $matter->id, (string) $client->visa_type);
            $this->assertSame('2025-12-31', $client->visaExpiry);
        }
    }
}
