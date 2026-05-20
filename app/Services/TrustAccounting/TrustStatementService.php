<?php

namespace App\Services\TrustAccounting;

use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rule 52: trust account statements per client/matter.
 */
class TrustStatementService
{
    /**
     * @return array<string, mixed>
     */
    public static function buildStatementContext(int $clientId, int $matterId, ?string $fromYmd = null, ?string $toYmd = null): array
    {
        $client = DB::table('admins')->where('id', $clientId)->first();
        if (! $client) {
            throw new \RuntimeException('Client not found.');
        }

        $matter = DB::table('client_matters')
            ->leftJoin('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
            ->where('client_matters.id', $matterId)
            ->where('client_matters.client_id', $clientId)
            ->select([
                'client_matters.client_unique_matter_no',
                'client_matters.case_detail',
                'matters.title as matter_name',
            ])
            ->first();

        if (! $matter) {
            throw new \RuntimeException('Matter not found for this client.');
        }

        $address = DB::table('client_addresses')
            ->where('client_id', $clientId)
            ->where('is_current', 1)
            ->first();
        if (! $address) {
            $address = DB::table('client_addresses')
                ->where('client_id', $clientId)
                ->orderByDesc('created_at')
                ->first();
        }

        $openingBalance = self::balanceBeforeDate($clientId, $matterId, $fromYmd);
        $entries = self::ledgerEntries($clientId, $matterId, $fromYmd, $toYmd);
        $closingBalance = self::balanceAsAt($clientId, $matterId, $toYmd);

        $clientRef = $client->client_id ?? (string) $clientId;
        $matterNo = $matter->client_unique_matter_no ?? '';
        $matterDisplay = trim(($matter->matter_name ?? '') . ($matterNo !== '' ? ' (' . $clientRef . '-' . $matterNo . ')' : ''));

        return [
            'client' => $client,
            'address' => $address,
            'matter' => $matter,
            'client_ref' => $clientRef,
            'matter_no' => $matterNo,
            'matter_display' => $matterDisplay !== '' ? $matterDisplay : $matterNo,
            'from_date' => $fromYmd,
            'to_date' => $toYmd,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'entries' => $entries,
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public static function pdfBinary(int $clientId, int $matterId, ?string $fromYmd = null, ?string $toYmd = null): string
    {
        $context = self::buildStatementContext($clientId, $matterId, $fromYmd, $toYmd);

        return PDF::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ])->loadView('crm.trust-accounting.statement-pdf', $context)->output();
    }

    public static function balanceAsAt(int $clientId, int $matterId, ?string $asAtYmd = null): float
    {
        $q = TrustReportQueryService::baseTrustLedgerQuery()
            ->where('client_id', $clientId)
            ->where('client_matter_id', $matterId);

        if ($asAtYmd) {
            TrustReportQueryService::applyTransDateUpTo($q, $asAtYmd);
        }

        $raw = $q->selectRaw(
            'SUM(COALESCE(deposit_amount, 0)::numeric - COALESCE(withdraw_amount, 0)::numeric) as total'
        )->value('total');

        return round((float) $raw, 2);
    }

    public static function balanceBeforeDate(int $clientId, int $matterId, ?string $fromYmd): float
    {
        if (! $fromYmd) {
            return 0.0;
        }

        $dayBefore = \Carbon\Carbon::parse($fromYmd)->subDay()->format('Y-m-d');

        return self::balanceAsAt($clientId, $matterId, $dayBefore);
    }

    /**
     * @return Collection<int, object>
     */
    public static function ledgerEntries(int $clientId, int $matterId, ?string $fromYmd, ?string $toYmd): Collection
    {
        $q = TrustReportQueryService::baseTrustLedgerQuery()
            ->where('client_id', $clientId)
            ->where('client_matter_id', $matterId);

        if ($fromYmd && $toYmd) {
            TrustReportQueryService::applyTransDateRange($q, $fromYmd, $toYmd);
        } elseif ($toYmd) {
            TrustReportQueryService::applyTransDateUpTo($q, $toYmd);
        }

        return $q->select([
            'trans_date',
            'trans_no',
            'client_fund_ledger_type',
            'description',
            'deposit_amount',
            'withdraw_amount',
            'balance_amount',
            'payment_method',
        ])
            ->orderByRaw("TO_DATE(trans_date, 'DD/MM/YYYY') ASC")
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Matters with non-zero trust balance as at date (Rule 52 annual run).
     *
     * @return Collection<int, object>
     */
    public static function mattersRequiringStatement(string $asAtYmd, bool $includeZero = false): Collection
    {
        return TrustMonthlyArchiveService::trialBalanceRows($asAtYmd, $includeZero);
    }
}
