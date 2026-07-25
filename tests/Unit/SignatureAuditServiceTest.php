<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\SignatureActivity;
use App\Models\Signer;
use App\Services\SignatureAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SignatureAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_logs_immutable_audit_events_with_request_context(): void
    {
        $document = Document::factory()->create(['status' => 'sent']);
        $signer = Signer::factory()->create([
            'document_id' => $document->id,
            'email' => 'signer@example.com',
            'name' => 'Test Signer',
            'status' => 'pending',
        ]);

        $request = Request::create('/sign/1/token', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_USER_AGENT' => 'PHPUnit-Agent',
        ]);

        $service = app(SignatureAuditService::class);
        $activity = $service->log(
            $document,
            'link_opened',
            'Signing link opened',
            ['signer_email' => $signer->email],
            $signer,
            SignatureAuditService::ACTOR_SIGNER,
            $request
        );

        $this->assertDatabaseHas('signature_activities', [
            'id' => $activity->id,
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'action_type' => 'link_opened',
            'actor_type' => 'signer',
            'ip_address' => '203.0.113.10',
        ]);

        $this->expectException(\RuntimeException::class);
        $activity->update(['note' => 'tampered']);
    }

    #[Test]
    public function it_records_completion_hashes_from_local_files(): void
    {
        $document = Document::factory()->create(['status' => 'sent', 'file_name' => 'Agreement.pdf']);
        $signer = Signer::factory()->create([
            'document_id' => $document->id,
            'email' => 'signer@example.com',
            'name' => 'Test Signer',
            'status' => 'pending',
            'opened_at' => now()->subHour(),
        ]);

        $original = tempnam(sys_get_temp_dir(), 'orig_') . '.pdf';
        $signed = tempnam(sys_get_temp_dir(), 'signed_') . '.pdf';
        $sigImg = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
        file_put_contents($original, '%PDF-1.4 original-content');
        file_put_contents($signed, '%PDF-1.4 signed-content');
        file_put_contents($sigImg, 'fakepng');

        $service = app(SignatureAuditService::class);
        $result = $service->recordCompletionEvidence(
            $document,
            $signer,
            $original,
            $signed,
            [$sigImg],
            Request::create('/documents/1/sign', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.5'])
        );

        $document->refresh();

        $this->assertSame(hash_file('sha256', $signed), $result['signed_hash']);
        $this->assertSame(hash_file('sha256', $original), $document->original_hash);
        $this->assertSame(hash_file('sha256', $signed), $document->signed_hash);
        $this->assertNotNull($document->hash_generated_at);
        $this->assertDatabaseHas('signature_activities', [
            'document_id' => $document->id,
            'action_type' => 'signed',
            'actor_type' => 'signer',
        ]);

        @unlink($original);
        @unlink($signed);
        @unlink($sigImg);
    }
}
