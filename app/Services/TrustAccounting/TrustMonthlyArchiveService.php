<?php

namespace App\Services\TrustAccounting;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Rule 38: immutable month-end report archives and auditor's pack bundles.
 */
class TrustMonthlyArchiveService
{
    public const TYPE_RECEIPTS = 'receipts_journal';
    public const TYPE_PAYMENTS = 'payments_journal';
    public const TYPE_TRIAL_BALANCE = 'trial_balance';

    /** @return list<string> */
    public static function archiveTypes(): array
    {
        return [self::TYPE_RECEIPTS, self::TYPE_PAYMENTS, self::TYPE_TRIAL_BALANCE];
    }

    public static function archiveExists(int $year, int $month, string $type): bool
    {
        if (! Schema::hasTable('trust_monthly_archives')) {
            return false;
        }

        return DB::table('trust_monthly_archives')
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('archive_type', $type)
            ->where('status', 'finalised')
            ->exists();
    }

    /**
     * @return array{created: int, skipped: int}
     */
    public static function createMonthArchives(int $year, int $month, int $staffId): array
    {
        if (! Schema::hasTable('trust_monthly_archives')) {
            throw new \RuntimeException('Monthly archives table is not available.');
        }

        $from = Carbon::create($year, $month, 1)->startOfMonth()->format('Y-m-d');
        $to = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');
        $created = 0;
        $skipped = 0;

        foreach (self::archiveTypes() as $type) {
            if (self::archiveExists($year, $month, $type)) {
                $skipped++;

                continue;
            }

            $csv = self::buildCsvForType($type, $from, $to);
            $filename = self::filenameForType($type, $from, $to);
            $documentId = self::storeCsvDocument($filename, $csv, $staffId);

            $archiveId = DB::table('trust_monthly_archives')->insertGetId([
                'period_year' => $year,
                'period_month' => $month,
                'archive_type' => $type,
                'prepared_by_staff_id' => $staffId,
                'prepared_at' => now(),
                'file_document_id' => $documentId,
                'status' => 'finalised',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            TrustLedgerAuditLogger::logForTable(
                'trust_monthly_archives',
                'month_archive_created',
                (int) $archiveId,
                'archive_type',
                null,
                $type,
                $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT)
            );

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public static function buildAuditorsPackZip(string $fromYmd, string $toYmd): string
    {
        $tmpdir = storage_path('app/trust-exam-pack-' . uniqid('', true));
        if (! is_dir($tmpdir) && ! mkdir($tmpdir, 0755, true)) {
            throw new \RuntimeException('Could not create temporary directory.');
        }

        foreach (self::archiveTypes() as $type) {
            $csv = self::buildCsvForType($type, $fromYmd, $toYmd);
            file_put_contents($tmpdir . DIRECTORY_SEPARATOR . self::filenameForType($type, $fromYmd, $toYmd), $csv);
        }

        $overdrawCsv = self::buildOverdrawnLedgerCsv($fromYmd, $toYmd);
        file_put_contents($tmpdir . DIRECTORY_SEPARATOR . 'trust-overdrawn-ledger-' . $fromYmd . '-to-' . $toYmd . '.csv', $overdrawCsv);

        $zipPath = storage_path('app/trust-exam-pack-' . $fromYmd . '-to-' . $toYmd . '-' . uniqid('', true) . '.zip');
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive.');
        }

        foreach (glob($tmpdir . DIRECTORY_SEPARATOR . '*.csv') ?: [] as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        foreach (glob($tmpdir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($tmpdir);

        return $zipPath;
    }

    private static function filenameForType(string $type, string $from, string $to): string
    {
        return match ($type) {
            self::TYPE_RECEIPTS => 'trust-receipts-journal-' . $from . '-to-' . $to . '.csv',
            self::TYPE_PAYMENTS => 'trust-payments-journal-' . $from . '-to-' . $to . '.csv',
            default => 'trust-trial-balance-as-at-' . $to . '.csv',
        };
    }

    private static function buildCsvForType(string $type, string $fromYmd, string $toYmd): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        if ($type === self::TYPE_RECEIPTS) {
            self::writeReceiptsJournalCsv($handle, $fromYmd, $toYmd);
        } elseif ($type === self::TYPE_PAYMENTS) {
            self::writePaymentsJournalCsv($handle, $fromYmd, $toYmd);
        } else {
            self::writeTrialBalanceCsv($handle, $toYmd);
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    /** @param resource $out */
    private static function writeReceiptsJournalCsv($out, string $fromYmd, string $toYmd): void
    {
        $q = TrustReportQueryService::baseTrustLedgerQuery();
        TrustReportQueryService::applyTransDateRange($q, $fromYmd, $toYmd, 'account_client_receipts.trans_date');
        $q->whereRaw('COALESCE(account_client_receipts.deposit_amount, 0)::numeric > 0');
        $q->join('admins', 'admins.id', '=', 'account_client_receipts.client_id')
            ->leftJoin('client_matters', 'client_matters.id', '=', 'account_client_receipts.client_matter_id')
            ->select([
                'account_client_receipts.trans_date',
                'account_client_receipts.trans_no',
                'account_client_receipts.client_fund_ledger_type',
                'admins.client_id as client_ref',
                'client_matters.client_unique_matter_no',
                'account_client_receipts.description',
                'account_client_receipts.deposit_amount',
                'account_client_receipts.payment_method',
                'account_client_receipts.invoice_no',
                'account_client_receipts.payer_name',
                'account_client_receipts.bank_deposit_reference',
                'account_client_receipts.banking_date',
            ])
            ->orderByRaw("TO_DATE(account_client_receipts.trans_date, 'DD/MM/YYYY') ASC");

        fputcsv($out, ['Trans date', 'Receipt no.', 'Type', 'Client ref', 'Matter', 'Description', 'Amount', 'Method', 'Invoice ref', 'Payer', 'Bank ref', 'Banking date']);
        foreach ($q->get() as $row) {
            fputcsv($out, [
                $row->trans_date,
                $row->trans_no,
                $row->client_fund_ledger_type,
                $row->client_ref,
                $row->client_unique_matter_no ?? '',
                (string) ($row->description ?? ''),
                number_format((float) $row->deposit_amount, 2, '.', ''),
                (string) ($row->payment_method ?? ''),
                (string) ($row->invoice_no ?? ''),
                $row->payer_name ?? '',
                $row->bank_deposit_reference ?? '',
                $row->banking_date ?? '',
            ]);
        }
    }

    /** @param resource $out */
    private static function writePaymentsJournalCsv($out, string $fromYmd, string $toYmd): void
    {
        $rule42 = Schema::hasTable('trust_withdrawal_authorities')
            && Schema::hasTable('trust_withdrawal_authority_types');

        $q = TrustReportQueryService::baseTrustLedgerQuery();
        TrustReportQueryService::applyTransDateRange($q, $fromYmd, $toYmd, 'account_client_receipts.trans_date');
        $q->whereRaw('COALESCE(account_client_receipts.withdraw_amount, 0)::numeric > 0');
        if ($rule42) {
            $q->leftJoin('trust_withdrawal_authorities as twa', 'twa.account_client_receipt_id', '=', 'account_client_receipts.id')
                ->leftJoin('trust_withdrawal_authority_types as twat', 'twat.id', '=', 'twa.authority_type_id');
        }
        $q->join('admins', 'admins.id', '=', 'account_client_receipts.client_id')
            ->leftJoin('client_matters', 'client_matters.id', '=', 'account_client_receipts.client_matter_id')
            ->select([
                'account_client_receipts.trans_date',
                'account_client_receipts.trans_no',
                'account_client_receipts.client_fund_ledger_type',
                'admins.client_id as client_ref',
                'client_matters.client_unique_matter_no',
                'account_client_receipts.description',
                'account_client_receipts.withdraw_amount',
                'account_client_receipts.payment_method',
                'account_client_receipts.invoice_no',
                'account_client_receipts.payee_name',
                'account_client_receipts.cheque_number',
                'account_client_receipts.eft_account_name',
                'account_client_receipts.eft_bsb',
                'account_client_receipts.eft_account_number',
                DB::raw($rule42 ? 'twat.label as rule42_type' : 'NULL::text as rule42_type'),
            ])
            ->orderByRaw("TO_DATE(account_client_receipts.trans_date, 'DD/MM/YYYY') ASC");

        fputcsv($out, ['Trans date', 'Receipt no.', 'Type', 'Client ref', 'Matter', 'Description', 'Amount', 'Method', 'Invoice ref', 'Payee', 'Cheque no.', 'EFT account', 'EFT BSB', 'EFT account no.', 'Rule 42 type']);
        foreach ($q->get() as $row) {
            fputcsv($out, [
                $row->trans_date,
                $row->trans_no,
                $row->client_fund_ledger_type,
                $row->client_ref,
                $row->client_unique_matter_no ?? '',
                (string) ($row->description ?? ''),
                number_format((float) $row->withdraw_amount, 2, '.', ''),
                (string) ($row->payment_method ?? ''),
                (string) ($row->invoice_no ?? ''),
                $row->payee_name ?? '',
                $row->cheque_number ?? '',
                $row->eft_account_name ?? '',
                $row->eft_bsb ?? '',
                $row->eft_account_number ?? '',
                $row->rule42_type ?? '',
            ]);
        }
    }

    /** @param resource $out */
    private static function writeTrialBalanceCsv($out, string $asAtYmd): void
    {
        $rows = self::trialBalanceRows($asAtYmd);
        fputcsv($out, ['Client ref', 'Matter', 'Name', 'Balance']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row->client_ref,
                $row->client_unique_matter_no ?? '',
                trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                number_format((float) $row->balance, 2, '.', ''),
            ]);
        }
    }

    public static function buildOverdrawnLedgerCsv(string $fromYmd, string $toYmd): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Trans date', 'Receipt no.', 'Type', 'Client ref', 'Matter', 'Amount', 'Resulting balance', 'Source']);

        foreach (self::overdrawnLedgerRows($fromYmd, $toYmd) as $row) {
            fputcsv($handle, [
                $row->trans_date,
                $row->trans_no,
                $row->client_fund_ledger_type,
                $row->client_ref,
                $row->client_unique_matter_no ?? '',
                number_format((float) $row->withdraw_amount, 2, '.', ''),
                number_format((float) $row->balance_amount, 2, '.', ''),
                $row->source ?? 'ledger',
            ]);
        }

        if (Schema::hasTable('trust_audit_logs')) {
            $auditRows = DB::table('trust_audit_logs')
                ->where('event', 'overdrawn_transaction_posted')
                ->whereDate('created_at', '>=', $fromYmd)
                ->whereDate('created_at', '<=', $toYmd)
                ->orderBy('created_at')
                ->get();
            foreach ($auditRows as $log) {
                fputcsv($handle, [
                    $log->created_at,
                    'AUDIT#' . $log->id,
                    'audit_log',
                    '',
                    '',
                    '',
                    $log->new_value ?? '',
                    $log->context ?? '',
                ]);
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }

    /**
     * @return Collection<int, object>
     */
    public static function overdrawnLedgerRows(?string $fromYmd = null, ?string $toYmd = null): Collection
    {
        $q = TrustReportQueryService::baseTrustLedgerQuery();
        $q->whereRaw('COALESCE(account_client_receipts.balance_amount, 0)::numeric < 0');
        if ($fromYmd && $toYmd) {
            TrustReportQueryService::applyTransDateRange($q, $fromYmd, $toYmd, 'account_client_receipts.trans_date');
        }
        $q->join('admins', 'admins.id', '=', 'account_client_receipts.client_id')
            ->leftJoin('client_matters', 'client_matters.id', '=', 'account_client_receipts.client_matter_id')
            ->select([
                'account_client_receipts.trans_date',
                'account_client_receipts.trans_no',
                'account_client_receipts.client_fund_ledger_type',
                'account_client_receipts.withdraw_amount',
                'account_client_receipts.balance_amount',
                'admins.client_id as client_ref',
                'client_matters.client_unique_matter_no',
                DB::raw("'ledger' as source"),
            ])
            ->orderByRaw("TO_DATE(account_client_receipts.trans_date, 'DD/MM/YYYY') ASC");

        return $q->get();
    }

    /**
     * @return Collection<int, object>
     */
    public static function trialBalanceRows(string $asAtYmd, bool $includeZero = false): Collection
    {
        $sub = TrustReportQueryService::baseTrustLedgerQuery();
        TrustReportQueryService::applyTransDateUpTo($sub, $asAtYmd, 'account_client_receipts.trans_date');
        $sub->select([
            'account_client_receipts.client_id',
            'account_client_receipts.client_matter_id',
            DB::raw('SUM(COALESCE(account_client_receipts.deposit_amount, 0)::numeric - COALESCE(account_client_receipts.withdraw_amount, 0)::numeric) as balance'),
        ])->groupBy('account_client_receipts.client_id', 'account_client_receipts.client_matter_id');

        $q = DB::query()->fromSub($sub, 'tb')
            ->join('admins', 'admins.id', '=', 'tb.client_id')
            ->leftJoin('client_matters', 'client_matters.id', '=', 'tb.client_matter_id')
            ->select([
                'admins.client_id as client_ref',
                'client_matters.client_unique_matter_no',
                'admins.first_name',
                'admins.last_name',
                'tb.balance',
                'tb.client_id',
                'tb.client_matter_id',
                'client_matters.trust_last_statement_sent_at',
            ])
            ->orderBy('admins.client_id')
            ->orderBy('client_matters.client_unique_matter_no');

        if (! $includeZero) {
            $q->whereRaw('ROUND(tb.balance::numeric, 2) <> 0');
        }

        return collect($q->get());
    }

    private static function storeCsvDocument(string $filename, string $csvContent, int $staffId): int
    {
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        $path = 'trust-archives/' . $safeName;
        Storage::disk('local')->put($path, $csvContent);

        $docId = DB::table('documents')->insertGetId([
            'file_name' => $filename,
            'filetype' => 'csv',
            'user_id' => $staffId ?: (Auth::guard('admin')->id() ?? 1),
            'myfile' => Storage::disk('local')->path($path),
            'myfile_key' => $safeName,
            'client_id' => null,
            'type' => 'trust_archive',
            'doc_type' => 'trust_archives',
            'file_size' => strlen($csvContent),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $docId;
    }
}
