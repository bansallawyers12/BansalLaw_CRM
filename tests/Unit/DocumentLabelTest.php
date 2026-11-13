<?php

namespace Tests\Unit;

use App\Support\DocumentLabel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentLabelTest extends TestCase
{
    #[Test]
    public function upload_name_is_client_reference_then_document_name_without_a_timestamp(): void
    {
        $stored = DocumentLabel::storedUploadName('CL-100', "JIGN2600231 Response To MDW", 'pdf');

        $this->assertSame('CL-100_JIGN2600231_Response_To_MDW', $stored['file_name']);
        $this->assertSame('CL-100_JIGN2600231_Response_To_MDW.pdf', $stored['storage_key']);
        $this->assertDoesNotMatchRegularExpression('/_\d{9,}$/', $stored['file_name']);
    }

    #[Test]
    public function empty_client_reference_falls_back_to_client_for_checklist_storage(): void
    {
        $this->assertSame('client_Passport', DocumentLabel::buildClientFileName('', 'Passport'));
    }

    #[Test]
    public function a_client_file_uses_the_reference_and_the_original_name(): void
    {
        $stored = DocumentLabel::clientFileNames('CL-100', 'Response To MDW.pdf');

        $this->assertSame('CL-100_Response_To_MDW', $stored['file_name']);
        $this->assertSame('CL-100_Response_To_MDW.pdf', $stored['storage_key']);
    }

    #[Test]
    public function a_file_with_no_client_keeps_the_original_name(): void
    {
        $stored = DocumentLabel::clientFileNames('', 'Contract Draft.pdf');

        $this->assertSame('Contract_Draft', $stored['file_name']);
        $this->assertSame('Contract_Draft.pdf', $stored['storage_key']);
    }

    #[Test]
    public function download_name_uses_the_saved_name_not_a_timestamped_storage_key(): void
    {
        $this->assertSame(
            'CL-100_Passport.pdf',
            DocumentLabel::downloadName('CL-100_Passport', 'pdf', '1792973396_5117_CL-100_Passport.pdf')
        );
        $this->assertSame(
            'Invoice-1042.pdf',
            DocumentLabel::downloadName('Invoice-1042.pdf', 'pdf', '1792973396_Invoice-1042.pdf')
        );
    }
}
