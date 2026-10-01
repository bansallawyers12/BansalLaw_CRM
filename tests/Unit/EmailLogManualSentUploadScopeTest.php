<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class EmailLogManualSentUploadScopeTest extends TestCase
{
    public function test_manual_sent_upload_scope_includes_document_mail_type_sent(): void
    {
        $sql = EmailLog::query()->manualSentUpload()->toSql();

        $this->assertStringContainsString('uploaded_doc_id', $sql);
        $this->assertStringContainsString('mail_body_type', $sql);
        $this->assertStringContainsString('documents', $sql);
        $this->assertStringContainsString('mail_type', $sql);
    }

    public function test_exclude_manual_sent_upload_wraps_negated_scope(): void
    {
        $sql = EmailLog::query()->excludeManualSentUpload()->toSql();

        $this->assertStringContainsString('not', strtolower($sql));
        $this->assertInstanceOf(Builder::class, EmailLog::query()->excludeManualSentUpload());
    }

    public function test_imported_sent_mail_scope_includes_body_type_and_document_sent(): void
    {
        $sql = EmailLog::query()->importedSentMail()->toSql();

        $this->assertStringContainsString('mail_body_type', $sql);
        $this->assertStringContainsString('uploaded_doc_id', $sql);
        $this->assertStringContainsString('documents', $sql);
    }
}
