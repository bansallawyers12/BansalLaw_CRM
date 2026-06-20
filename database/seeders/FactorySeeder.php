<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Document;
use App\Models\Email;
use App\Models\Lead;
use App\Models\Signer;
use App\Models\Staff;
use Illuminate\Database\Seeder;

class FactorySeeder extends Seeder
{
    /**
     * Seed sample data for all models that have factories.
     */
    public function run(): void
    {
        $staffId = Staff::query()->value('id');

        $clients = Admin::factory(10)->create();

        Lead::factory(8)->asNewLead()->create(['user_id' => $staffId]);
        Lead::factory(4)->followUp()->create(['user_id' => $staffId]);
        Lead::factory(2)->converted()->create(['user_id' => $staffId]);

        Email::factory(5)->create();

        $documents = Document::factory(10)->create([
            'created_by' => $staffId,
            'client_id' => fn () => $clients->random()->id,
        ]);

        foreach ($documents as $document) {
            Signer::factory(2)->create(['document_id' => $document->id]);
        }

        $this->command?->info('Factory data seeded: 10 clients, 14 leads, 5 emails, 10 documents, 20 signers.');
    }
}
