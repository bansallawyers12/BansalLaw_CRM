<?php

namespace App\Services;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\Lead;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class LeadSpreadsheetImportService
{
    /** @var array<string, list<string>> */
    private const COLUMN_ALIASES = [
        'first_name' => ['first_name', 'first name', 'firstname', 'given name'],
        'last_name' => ['last_name', 'last name', 'lastname', 'surname', 'family name'],
        'name' => ['name', 'full name', 'lead name', 'client name'],
        'email' => ['email', 'email address', 'e-mail', 'email id'],
        'phone' => ['phone', 'phone number', 'mobile', 'mobile number', 'contact number', 'contact no', 'telephone'],
        'gender' => ['gender', 'sex'],
        'dob' => ['dob', 'date of birth', 'birth date', 'birthdate'],
        'lead_status' => ['lead_status', 'stage', 'status', 'lead stage', 'pipeline stage'],
        'followup_date' => ['followup_date', 'follow up date', 'follow-up date', 'contact date', 'next follow up'],
        'source' => ['source', 'lead source', 'referral source'],
        'notes' => ['notes', 'note', 'comments', 'comment', 'remarks'],
        'country_code' => ['country_code', 'country code', 'dial code'],
    ];

    /** @var array<string, string> */
    private const LEAD_STATUS_MAP = [
        'new' => 'new',
        'new enquiry' => 'new',
        'new inquiry' => 'new',
        'enquiry' => 'new',
        'inquiry' => 'new',
        'follow up' => 'follow_up',
        'follow-up' => 'follow_up',
        'follow_up' => 'follow_up',
        'followup' => 'follow_up',
        'not qualified' => 'not_qualified',
        'not_qualified' => 'not_qualified',
        'not proceeding' => 'not_qualified',
        'declined' => 'not_qualified',
        'hostile' => 'hostile',
    ];

    public function __construct(
        private readonly ClientReferenceService $referenceService,
        private readonly LeadFollowUpNoteService $followUpNoteService,
        private readonly LeadDuplicateCheckService $duplicateCheckService
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     imported: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<string>,
     *     message: string
     * }
     */
    public function importFromFile(UploadedFile $file, bool $skipDuplicates = true): array
    {
        $rows = $this->parseFile($file);

        if ($rows === []) {
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => ['The file is empty or has no data rows.'],
                'message' => 'The file is empty or has no data rows.',
            ];
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $seenEmails = [];
        $seenPhones = [];

        foreach ($rows as $rowNumber => $row) {
            $line = $rowNumber + 2;

            try {
                $leadData = $this->mapRowToLeadData($row);
                if ($leadData === null) {
                    continue;
                }

                $duplicateReason = $this->resolveDuplicateReason(
                    $leadData['email'],
                    $leadData['phone'],
                    $seenEmails,
                    $seenPhones
                );

                if ($duplicateReason !== null) {
                    $this->registerSeenContact($leadData['email'], $leadData['phone'], $seenEmails, $seenPhones);

                    if ($skipDuplicates) {
                        $skipped++;
                        $errors[] = "Row {$line}: {$duplicateReason} Skipped.";
                        continue;
                    }

                    throw new \InvalidArgumentException($duplicateReason);
                }

                $this->registerSeenContact($leadData['email'], $leadData['phone'], $seenEmails, $seenPhones);
                $this->createLead($leadData);
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Row {$line}: {$e->getMessage()}";
            }
        }

        if ($imported === 0 && $skipped === 0 && $failed === 0) {
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => ['No valid lead rows were found. Ensure the file has a header row and at least one row with first name and email or phone.'],
                'message' => 'No valid lead rows were found.',
            ];
        }

        $parts = [];
        if ($imported > 0) {
            $parts[] = "{$imported} imported";
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} skipped (duplicate)";
        }
        if ($failed > 0) {
            $parts[] = "{$failed} failed";
        }

        return [
            'success' => $imported > 0 || ($skipped > 0 && $failed === 0),
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'message' => 'Lead import complete: ' . implode(', ', $parts) . '.',
        ];
    }

    /**
     * @param array<string, mixed> $leadData
     */
    private function createLead(array $leadData): void
    {
        DB::beginTransaction();

        try {
            $reference = $this->referenceService->generateClientReference($leadData['first_name']);
            $pipelineStatus = $leadData['lead_status'] ?? 'new';
            $followupDb = null;

            if ($pipelineStatus === 'follow_up' && ! empty($leadData['followup_date'])) {
                $followupDb = $this->parseDateTime($leadData['followup_date']);
            }

            $adminData = [
                'password' => Hash::make('LEAD_PLACEHOLDER'),
                'client_counter' => $reference['client_counter'],
                'client_id' => $reference['client_id'],
                'status' => LeadFollowUpNoteService::adminsStatusForLeadStatus($pipelineStatus),
                'lead_status' => $pipelineStatus,
                'followup_date' => $followupDb,
                'type' => 'lead',
                'is_archived' => 0,
                'is_deleted' => null,
                'australian_study' => 0,
                'specialist_education' => 0,
                'regional_study' => 0,
                'is_company' => 0,
                'first_name' => $leadData['first_name'],
                'last_name' => $leadData['last_name'] ?? null,
                'gender' => $leadData['gender'] ?? null,
                'dob' => $this->parseDate($leadData['dob'] ?? null),
                'phone' => $leadData['phone'] ?? null,
                'email' => $leadData['email'],
                'country_code' => $leadData['country_code'] ?? null,
                'source' => $leadData['source'] ?? null,
                'contact_type' => 'Personal',
                'email_type' => 'Personal',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->applyLeadAssigneeToAdminRow($adminData, (int) Auth::id());
            if (Schema::hasColumn('admins', 'is_other_party')) {
                $adminData['is_other_party'] = 0;
            }
            $this->pruneAdminInsertData($adminData);

            $leadId = DB::table('admins')->insertGetId($adminData);

            if (! empty($leadData['phone'])) {
                ClientContact::create([
                    'admin_id' => Auth::id(),
                    'client_id' => $leadId,
                    'contact_type' => 'Personal',
                    'phone' => $leadData['phone'],
                    'country_code' => $leadData['country_code'] ?? '',
                    'is_verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! empty($leadData['email']) && ! str_contains(strtolower($leadData['email']), '@lead.internal')) {
                ClientEmail::create([
                    'admin_id' => Auth::id(),
                    'client_id' => $leadId,
                    'email_type' => 'Personal',
                    'email' => $leadData['email'],
                    'is_verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! empty($leadData['notes'])) {
                $noteAttrs = [
                    'client_id' => $leadId,
                    'created_by' => Auth::id(),
                    'subject' => 'Lead import – notes',
                    'description' => '<p>' . nl2br(e($leadData['notes'])) . '</p>',
                    'activity_type' => 'note',
                    'task_status' => 0,
                    'pin' => 0,
                ];
                if (Schema::hasColumn('activities_logs', 'use_for')) {
                    $noteAttrs['use_for'] = null;
                }
                ActivitiesLog::create($noteAttrs);
            }

            $lead = Lead::find($leadId);
            if ($lead) {
                $this->followUpNoteService->syncNotesForLead($lead, null);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, true> $seenEmails
     * @param array<string, true> $seenPhones
     */
    private function resolveDuplicateReason(
        ?string $email,
        ?string $phone,
        array $seenEmails,
        array $seenPhones
    ): ?string {
        if ($this->duplicateCheckService->isCheckableEmail($email)) {
            $emailKey = strtolower(trim((string) $email));
            if (isset($seenEmails[$emailKey])) {
                return 'Duplicate row in file: same email (' . trim((string) $email) . ').';
            }
        }

        $phoneKey = $this->duplicateCheckService->normalizePhoneDigits($phone);
        if ($phoneKey !== '' && isset($seenPhones[$phoneKey])) {
            return 'Duplicate row in file: same phone (' . trim((string) $phone) . ').';
        }

        $duplicate = $this->duplicateCheckService->findDuplicate($email, $phone);
        if ($duplicate !== null) {
            return 'Duplicate lead in CRM: same ' . $duplicate['match'] . ' (' . $duplicate['value'] . ').';
        }

        return null;
    }

    /**
     * @param array<string, true> $seenEmails
     * @param array<string, true> $seenPhones
     */
    private function registerSeenContact(?string $email, ?string $phone, array &$seenEmails, array &$seenPhones): void
    {
        if ($this->duplicateCheckService->isCheckableEmail($email)) {
            $seenEmails[strtolower(trim((string) $email))] = true;
        }

        $phoneKey = $this->duplicateCheckService->normalizePhoneDigits($phone);
        if ($phoneKey !== '') {
            $seenPhones[$phoneKey] = true;
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function parseFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'csv' => $this->parseCsv($file),
            'xlsx', 'xls' => $this->parseExcel($file),
            default => throw new \InvalidArgumentException('Unsupported spreadsheet format.'),
        };
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to read the CSV file.');
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($data)) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normalizeHeaders($data);
                continue;
            }

            $rows[] = $this->combineRow($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function parseExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        $headers = null;
        $rows = [];

        foreach ($rawRows as $rawRow) {
            if ($this->isEmptyRow($rawRow)) {
                continue;
            }

            $normalizedRow = array_map(fn ($cell) => $this->normalizeCellValue($cell), $rawRow);

            if ($headers === null) {
                $headers = $this->normalizeHeaders($normalizedRow);
                continue;
            }

            $rows[] = $this->combineRow($headers, $normalizedRow);
        }

        return $rows;
    }

    /**
     * @param list<string|null> $headers
     * @param list<mixed> $values
     * @return array<string, string|null>
     */
    private function combineRow(array $headers, array $values): array
    {
        $row = [];
        $columnCount = max(count($headers), count($values));

        for ($i = 0; $i < $columnCount; $i++) {
            $header = $headers[$i] ?? 'column_' . ($i + 1);
            $value = $values[$i] ?? null;
            $row[$header] = $this->stringValue($value);
        }

        return $row;
    }

    /**
     * @param list<mixed> $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header, $index) {
            $label = trim((string) $header);
            if ($label === '') {
                return 'column_' . ($index + 1);
            }

            return strtolower(preg_replace('/\s+/', ' ', $label) ?? $label);
        }, $headers, array_keys($headers));
    }

    /**
     * @param array<string, string|null> $row
     * @return array<string, mixed>|null
     */
    private function mapRowToLeadData(array $row): ?array
    {
        $mapped = [];
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            $mapped[$field] = $this->valueFromRow($row, $aliases);
        }

        if ($mapped['first_name'] === null && $mapped['name'] !== null) {
            [$first, $last] = $this->splitName($mapped['name']);
            $mapped['first_name'] = $first;
            $mapped['last_name'] = $mapped['last_name'] ?? $last;
        }

        if ($this->isBlank($mapped['first_name'])) {
            return null;
        }

        if ($this->isBlank($mapped['email']) && $this->isBlank($mapped['phone'])) {
            throw new \InvalidArgumentException('Each lead row must include an email or phone number.');
        }

        $email = trim((string) ($mapped['email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $this->generatePlaceholderEmail((string) ($mapped['phone'] ?? ''));
        }

        return [
            'first_name' => trim((string) $mapped['first_name']),
            'last_name' => $this->blankToNull($mapped['last_name']),
            'email' => $email,
            'phone' => $this->blankToNull($mapped['phone']),
            'gender' => $this->blankToNull($mapped['gender']),
            'dob' => $this->blankToNull($mapped['dob']),
            'country_code' => $this->blankToNull($mapped['country_code']),
            'source' => $this->blankToNull($mapped['source']),
            'lead_status' => $this->normalizeLeadStatus($mapped['lead_status']) ?? 'new',
            'followup_date' => $this->blankToNull($mapped['followup_date']),
            'notes' => $this->blankToNull($mapped['notes']),
        ];
    }

    /**
     * @param array<string, string|null> $row
     * @param list<string> $aliases
     */
    private function valueFromRow(array $row, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row)) {
                $value = $this->blankToNull($row[$alias]);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?? $fullName);
        $parts = explode(' ', $fullName, 2);

        return [
            $parts[0],
            $parts[1] ?? null,
        ];
    }

    private function normalizeLeadStatus(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim(str_replace('_', ' ', $value)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if (isset(self::LEAD_STATUS_MAP[$normalized])) {
            return self::LEAD_STATUS_MAP[$normalized];
        }

        $slug = strtolower(str_replace(' ', '_', $value));
        if (in_array($slug, LeadFollowUpNoteService::pipelineStatuses(), true)) {
            return $slug;
        }

        return 'new';
    }

    private function generatePlaceholderEmail(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '0';

        do {
            $candidate = 'import_' . $digits . '_' . time() . '_' . bin2hex(random_bytes(3)) . '@lead.internal';
        } while (Admin::query()->whereRaw('LOWER(email) = ?', [strtolower($candidate)])->exists());

        return $candidate;
    }

    private function parseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function parseDateTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime(trim($value));

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function applyLeadAssigneeToAdminRow(array &$adminData, int $assignUserId): void
    {
        if (Schema::hasColumn('admins', 'user_id')) {
            $adminData['user_id'] = $assignUserId;
        } elseif (Schema::hasColumn('admins', 'agent_id')) {
            $adminData['agent_id'] = $assignUserId;
        }
    }

    private function pruneAdminInsertData(array &$adminData): void
    {
        foreach (array_keys($adminData) as $col) {
            if (! Schema::hasColumn('admins', $col)) {
                unset($adminData[$col]);
            }
        }
    }

    private function normalizeCellValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value) && (float) $value > 1 && (float) $value < 60000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                // Not an Excel serial date — fall through to string cast.
            }
        }

        return trim((string) $value);
    }

    /**
     * @param list<mixed> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (! $this->isBlank($value)) {
                return false;
            }
        }

        return true;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeCellValue($value);

        return $this->blankToNull($normalized);
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function isBlank(mixed $value): bool
    {
        return $this->blankToNull(is_string($value) || $value === null ? $value : (string) $value) === null;
    }

    public static function templateCsvContent(): string
    {
        $headers = [
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Gender',
            'Date of Birth',
            'Stage',
            'Contact Date',
            'Source',
            'Notes',
        ];

        $sample = [
            'John',
            'Smith',
            'john.smith@example.com',
            '0412345678',
            'Male',
            '15/03/1990',
            'New Enquiry',
            '22/07/2026 10:00',
            'Website',
            'Interested in family law consultation',
        ];

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        fputcsv($stream, $headers);
        fputcsv($stream, $sample);
        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $content;
    }
}
