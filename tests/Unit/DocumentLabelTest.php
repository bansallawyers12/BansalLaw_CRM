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
    public function empty_client_reference_falls_back_to_client(): void
    {
        $this->assertSame('client_Passport', DocumentLabel::buildClientFileName('', 'Passport'));
    }
}
