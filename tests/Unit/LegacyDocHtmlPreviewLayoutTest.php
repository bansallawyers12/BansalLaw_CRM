<?php

namespace Tests\Unit;

use App\Services\LegacyDocHtmlPreviewService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyDocHtmlPreviewLayoutTest extends TestCase
{
    #[Test]
    public function renders_inline_party_roles_and_loose_meta_columns(): void
    {
        // Simulate piece-table text after OLE extraction (tabs/cell marks may be absent).
        $text = "IN THE SUPREME COURT OF VICTORIA\r"
            ."COMMERCIAL COURT\r"
            ."CORPORATIONS LIST\r"
            ."S ECI 2026 03562\r"
            ."BETWEEN\r"
            ."RACHIT SALVI AND VIRAJ SALVI (First and Second Plaintiffs)\r"
            ."and\r"
            ."SK ROAYL PTY LTD (CAN 155 735 096) (The Company)\r"
            ."AFFIDAVIT OF AJAIPAL SINGH\r"
            ."Date of Document: 8 September 2026\tSolicitors Code: CR120722\r"
            ."Filed on behalf of: The Defendant\tTelephone: 1300 226 725\r"
            ."Prepared by: Bansal Lawyers, Level 8/278 Collins Street, Melbourne VIC 3000 Ref: AJAY2600063 / SC_1\r"
            ."Email: ajay@bansallawyers.com.au\r"
            ."I, AJAIPAL SINGH make oath and say:\r"
            ."I am the defendant.\r"
            ."DISPUTED AMOUNT IN TOTALITY\r"
            ."Further facts.\r";

        $svc = new LegacyDocHtmlPreviewService();
        $ref = new \ReflectionClass($svc);
        $render = $ref->getMethod('renderStructuredHtml');
        $render->setAccessible(true);
        $html = $render->invoke($svc, $text);

        $this->assertStringContainsString('doc-party', $html);
        $this->assertStringContainsString('First and Second Plaintiffs', $html);
        $this->assertStringContainsString('The Company', $html);
        $this->assertStringContainsString('doc-meta', $html);
        $this->assertStringContainsString('Solicitors Code', $html);
        $this->assertStringContainsString('Ref:', $html);
        $this->assertStringContainsString('doc-caption', $html);
        $this->assertStringContainsString('doc-title', $html);
        $this->assertStringContainsString('doc-num', $html);
        $this->assertStringContainsString('doc-section', $html);
    }
}
