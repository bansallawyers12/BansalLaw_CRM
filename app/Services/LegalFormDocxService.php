<?php

namespace App\Services;

use App\Models\ClientLegalForm;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;

class LegalFormDocxService
{
    private string $templatesDir;

    public function __construct()
    {
        $this->templatesDir = storage_path('app/templates/legal_forms');
    }

    private function ensureTemplatesDirectory(): void
    {
        if (! is_dir($this->templatesDir)) {
            mkdir($this->templatesDir, 0755, true);
        }
    }

    /**
     * First-time setup: Word templates are not committed to the repo. Build minimal
     * .docx stubs so TemplateProcessor can run; replace with firm-branded files anytime.
     */
    private function ensureCostAgreementTemplate(): void
    {
        $path = $this->templatesDir . '/cost_agreement_template.docx';
        if (is_file($path)) {
            return;
        }

        $placeholders = [
            'CLIENT_NAME', 'CLIENT_ADDRESS', 'FORM_DATE_LONG', 'FORM_DATE_SHORT', 'MATTER_REFERENCE',
            'PERSON_RESPONSIBLE', 'PERSON_RESPONSIBLE_EMAIL', 'FIXED_FEE_AMOUNT', 'ESTIMATED_TOTAL',
            'RETAINER_AMOUNT', 'SCOPE_OF_WORK', 'VARIABLES_AFFECTING_COSTS',
            'COSTS_BREAKDOWN_FEES', 'COSTS_BREAKDOWN_DISBURSEMENTS',
        ];

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addTitle('Cost agreement', 1);
        foreach ($placeholders as $name) {
            $section->addText('${' . $name . '}');
            $section->addTextBreak();
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    }

    private function ensureAuthorityToActTemplate(): void
    {
        $path = $this->templatesDir . '/authority_to_act_template.docx';
        if (is_file($path)) {
            return;
        }

        $placeholders = ['CLIENT_NAME', 'CLIENT_ADDRESS', 'MATTER_REFERENCE', 'FORM_DATE_SHORT', 'AUTHORITY_SCOPE', 'AUTHORITY_ITEMS'];

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addTitle('Authority to act', 1);
        foreach ($placeholders as $name) {
            $section->addText('${' . $name . '}');
            $section->addTextBreak();
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
    }

    public function generate(ClientLegalForm $form): string
    {
        $form->load(['client', 'matter']);

        return match ($form->form_type) {
            'cost_agreement' => $this->generateCostAgreement($form),
            'short_costs_disclosure' => $this->generateShortCostsDisclosure($form),
            'authority_to_act' => $this->generateAuthorityToAct($form),
        };
    }

    private function generateCostAgreement(ClientLegalForm $form): string
    {
        $this->ensureTemplatesDirectory();
        $this->ensureCostAgreementTemplate();

        $tp = new TemplateProcessor($this->templatesDir . '/cost_agreement_template.docx');

        $client = $form->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $clientAddress = collect([$client->address, $client->city, $client->state, $client->zip])
            ->filter()->implode(', ');
        $matterRef = $form->matter_reference ?? ($form->matter ? $form->matter->client_unique_matter_no : '');

        $tp->setValue('CLIENT_NAME', $clientName);
        $tp->setValue('CLIENT_ADDRESS', $clientAddress);
        $tp->setValue('FORM_DATE_LONG', $form->form_date ? $form->form_date->format('j F Y') : now()->format('j F Y'));
        $tp->setValue('FORM_DATE_SHORT', $form->form_date ? $form->form_date->format('d/m/Y') : now()->format('d/m/Y'));
        $tp->setValue('MATTER_REFERENCE', $matterRef);
        $tp->setValue('PERSON_RESPONSIBLE', $form->person_responsible ?? '');
        $tp->setValue('PERSON_RESPONSIBLE_EMAIL', $form->person_responsible_email ?? '');
        $tp->setValue('FIXED_FEE_AMOUNT', '$' . number_format($form->fixed_fee_amount ?? 0, 2));
        $tp->setValue('ESTIMATED_TOTAL', '$' . number_format($form->estimated_total ?? 0, 2));
        $tp->setValue('RETAINER_AMOUNT', '$' . number_format($form->retainer_amount ?? 0, 2));
        $tp->setValue('SCOPE_OF_WORK', $form->scope_of_work ?? '');
        $tp->setValue('VARIABLES_AFFECTING_COSTS', $form->variables_affecting_costs ?? '');
        $tp->setValue('COSTS_BREAKDOWN_FEES', '$' . number_format($form->estimated_legal_fees ?? 0, 2) . ' for our fees;');
        $tp->setValue('COSTS_BREAKDOWN_DISBURSEMENTS', '$' . number_format($form->estimated_disbursements ?? 0, 2) . ' for disbursements;');

        return $this->saveFromTemplate($tp, $form);
    }

    private function generateShortCostsDisclosure(ClientLegalForm $form): string
    {
        $this->ensureTemplatesDirectory();

        $templatePath = $this->templatesDir . '/short_costs_disclosure_template.docx';
        if (! is_file($templatePath)) {
            return $this->generateShortCostsDisclosureAsPhpWord($form);
        }

        $client = $form->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $clientAddress = collect([$client->address, $client->city, $client->state, $client->zip])
            ->filter()->implode(', ');
        $formDate = $form->form_date ? $form->form_date->format('d/m/Y') : now()->format('d/m/Y');

        $dir = 'legal_forms/' . $form->client_id;
        $fullDir = public_path($dir);
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
        $filename = $form->form_type . '_' . $form->id . '.docx';
        $path = $dir . '/' . $filename;
        if (! @copy($templatePath, public_path($path))) {
            return $this->generateShortCostsDisclosureAsPhpWord($form);
        }

        // Map form field index (1-based) to values.
        // Field order: 1=Date, 2=Firm, 3=Contact, 4=FirmAddress, 5=FirmPhone, 6=FirmMobile,
        // 7=FirmState, 8=FirmPostcode, 9=FirmEmail, 10=ClientName, 11=ClientPhone,
        // 12=ClientAddress, 13=ClientMobile, 14=ClientEmail, 15=ClientState, 16=ClientPostcode,
        // 17=Scope, 18=LegalFees, 19=BasisCalc1, 20=BasisCalc2, 21=Disbursements,
        // 22=ItemisedDisb, 23=OptItemisedDisb, 24=BarristerFees, 25=GST, 26=Total
        $fieldValuesByIndex = [
            1 => $formDate,
            2 => $form->firm_name ?? 'Bansal Lawyers',
            3 => $form->firm_contact ?? ($form->person_responsible ?? 'Ajay Bansal'),
            4 => $form->firm_address ?? 'Level 8, 278 Collins Street, Melbourne VIC 3000',
            5 => $form->firm_phone ?? '0422 905 860',
            6 => $form->firm_mobile ?? '',
            7 => $form->firm_state ?? 'VIC',
            8 => $form->firm_postcode ?? '3000',
            9 => $form->firm_email ?? 'info@bansallawyers.com.au',
            10 => $clientName,
            11 => $client->phone ?? '',
            12 => $clientAddress,
            13 => $client->mobile ?? '',
            14 => $client->email ?? '',
            15 => $client->state ?? '',
            16 => $client->zip ?? '',
            17 => $form->scope_of_work ?? '',
            18 => number_format($form->estimated_legal_fees ?? 0, 2),
            21 => number_format($form->estimated_disbursements ?? 0, 2),
            24 => number_format($form->estimated_barrister_fees ?? 0, 2),
            25 => number_format($form->gst_amount ?? 0, 2),
            26 => number_format($form->estimated_total ?? 0, 2),
        ];

        $this->replaceAllFormFields(public_path($path), $fieldValuesByIndex);

        return $path;
    }

    /**
     * Fallback when short_costs_disclosure_template.docx is not deployed (no form fields).
     */
    private function generateShortCostsDisclosureAsPhpWord(ClientLegalForm $form): string
    {
        $client = $form->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $clientAddress = collect([$client->address, $client->city, $client->state, $client->zip])
            ->filter()->implode(', ');
        $formDate = $form->form_date ? $form->form_date->format('d/m/Y') : now()->format('d/m/Y');

        $dir = 'legal_forms/' . $form->client_id;
        $fullDir = public_path($dir);
        if (! is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
        $filename = $form->form_type . '_' . $form->id . '.docx';
        $relativePath = $dir . '/' . $filename;
        $fullPath = public_path($relativePath);

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addTitle('Short Form Disclosure of Costs', 1);
        $section->addTextBreak();

        $rows = [
            ['Date', $formDate],
            ['Law practice', $form->firm_name ?? 'Bansal Lawyers'],
            ['Contact', $form->firm_contact ?? ($form->person_responsible ?? '')],
            ['Practice address', $form->firm_address ?? ''],
            ['Phone', $form->firm_phone ?? ''],
            ['Mobile', $form->firm_mobile ?? ''],
            ['State', $form->firm_state ?? ''],
            ['Postcode', $form->firm_postcode ?? ''],
            ['Email', $form->firm_email ?? ''],
            ['Client name', $clientName],
            ['Client phone', $client->phone ?? ''],
            ['Client address', $clientAddress],
            ['Client mobile', $client->mobile ?? ''],
            ['Client email', $client->email ?? ''],
            ['Client state', $client->state ?? ''],
            ['Client postcode', $client->zip ?? ''],
            ['Scope of work', $form->scope_of_work ?? ''],
            ['Legal fees (ex GST)', number_format($form->estimated_legal_fees ?? 0, 2)],
            ['Disbursements (ex GST)', number_format($form->estimated_disbursements ?? 0, 2)],
            ['Barrister fees (ex GST)', number_format($form->estimated_barrister_fees ?? 0, 2)],
            ['GST', number_format($form->gst_amount ?? 0, 2)],
            ['Total estimate', number_format($form->estimated_total ?? 0, 2)],
        ];

        foreach ($rows as [$label, $value]) {
            $section->addText($label . ': ', ['bold' => true]);
            $section->addText((string) $value);
            $section->addTextBreak();
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($fullPath);

        return $relativePath;
    }

    /**
     * Replace FORMTEXT field values in a .docx by sequential field index (1-based).
     * The SCD template has 26 form fields in a fixed order.
     */
    private function replaceAllFormFields(string $docxPath, array $fieldValuesByIndex): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }
        $xml = $zip->getFromName('word/document.xml');

        $dom = new \DOMDocument;
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $fldChars = $xpath->query('//w:fldChar');
        $current = null;
        $fieldIndex = 0;

        foreach ($fldChars as $fc) {
            $type = $fc->getAttribute('w:fldCharType');
            if ($type === 'begin') {
                $fieldIndex++;
                $current = ['index' => $fieldIndex];
            } elseif ($type === 'separate' && $current) {
                $current['separateRun'] = $fc->parentNode;
            } elseif ($type === 'end' && $current) {
                if (isset($current['separateRun']) && isset($fieldValuesByIndex[$current['index']])) {
                    $this->setFormFieldValue($dom, $current['separateRun'], $fc->parentNode, $fieldValuesByIndex[$current['index']]);
                }
                $current = null;
            }
        }

        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->close();
    }

    private function setFormFieldValue(\DOMDocument $dom, \DOMElement $sepRun, \DOMElement $endRun, string $value): void
    {
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $sepPara = $sepRun->parentNode;
        $endPara = $endRun->parentNode;

        if ($sepPara === $endPara) {
            $node = $sepRun->nextSibling;
            while ($node && $node !== $endRun) {
                $next = $node->nextSibling;
                $sepPara->removeChild($node);
                $node = $next;
            }
            $newRun = $this->createTextRun($dom, $value);
            $sepPara->insertBefore($newRun, $endRun);
            return;
        }

        // Cross-paragraph field: separate and end are in different paragraphs
        $node = $sepRun->nextSibling;
        while ($node) {
            $next = $node->nextSibling;
            $sepRun->parentNode->removeChild($node);
            $node = $next;
        }

        $newRun = $this->createTextRun($dom, $value);
        $sepPara->appendChild($newRun);

        $container = $sepPara->parentNode;
        $para = $sepPara->nextSibling;
        while ($para && $para !== $endPara) {
            $next = $para->nextSibling;
            $container->removeChild($para);
            $para = $next;
        }

        // In end paragraph, move the end fldChar run to the separate paragraph
        // and remove the end paragraph
        $endPara->removeChild($endRun);
        $sepPara->appendChild($endRun);
        if ($endPara->parentNode) {
            $endPara->parentNode->removeChild($endPara);
        }
    }

    private function createTextRun(\DOMDocument $dom, string $value): \DOMElement
    {
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $newRun = $dom->createElementNS($ns, 'w:r');
        $rPr = $dom->createElementNS($ns, 'w:rPr');
        $rPr->appendChild($dom->createElementNS($ns, 'w:noProof'));
        $newRun->appendChild($rPr);
        $t = $dom->createElementNS($ns, 'w:t');
        $t->setAttribute('xml:space', 'preserve');
        $t->appendChild($dom->createTextNode($value));
        $newRun->appendChild($t);
        return $newRun;
    }

    private function generateAuthorityToAct(ClientLegalForm $form): string
    {
        $this->ensureTemplatesDirectory();
        $this->ensureAuthorityToActTemplate();

        $tp = new TemplateProcessor($this->templatesDir . '/authority_to_act_template.docx');

        $client = $form->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $clientAddress = collect([$client->address, $client->city, $client->state, $client->zip])
            ->filter()->implode(', ');
        $matterRef = $form->matter_reference ?? ($form->matter ? $form->matter->client_unique_matter_no : '');

        $tp->setValue('CLIENT_NAME', $clientName);
        $tp->setValue('CLIENT_ADDRESS', $clientAddress);
        $tp->setValue('MATTER_REFERENCE', $matterRef);
        $tp->setValue('FORM_DATE_SHORT', $form->form_date ? $form->form_date->format('d/m/Y') : now()->format('d/m/Y'));

        $scopeText = $form->authority_scope ?: ($form->scope_of_work ?? '');
        $tp->setValue('AUTHORITY_SCOPE', $scopeText);
        $tp->setValue('AUTHORITY_ITEMS', '');

        return $this->saveFromTemplate($tp, $form);
    }

    private function saveFromTemplate(TemplateProcessor $tp, ClientLegalForm $form): string
    {
        $dir = 'legal_forms/' . $form->client_id;
        $fullDir = public_path($dir);
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $filename = $form->form_type . '_' . $form->id . '.docx';
        $relativePath = $dir . '/' . $filename;
        $tp->saveAs(public_path($relativePath));

        return $relativePath;
    }
}
