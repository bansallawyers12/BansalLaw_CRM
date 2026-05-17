<?php

namespace App\Services\TrustAccounting;

use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Uniform Law Rule 42: capture withdrawal authority for Fee Transfer rows.
 */
class TrustWithdrawalAuthorityService
{
    public static function isEnforcementActive(): bool
    {
        return Schema::hasTable('trust_withdrawal_authorities')
            && Schema::hasTable('trust_withdrawal_authority_types');
    }

    /**
     * @param  array<string, mixed>  $requestData
     * @return array{type_id: int, notice_given_date: ?string, notes: ?string, override: bool, reason: string}
     */
    public static function parseAuthorityFromRequest(array $requestData): array
    {
        $typeId = (int) ($requestData['trust_withdrawal_authority_type_id'] ?? 0);
        $notice = isset($requestData['trust_notice_given_date']) ? trim((string) $requestData['trust_notice_given_date']) : '';
        $notes = isset($requestData['trust_authority_notes']) ? trim((string) $requestData['trust_authority_notes']) : '';
        $override = ! empty($requestData['trust_rule42_supervisor_override'])
            || ($requestData['trust_rule42_supervisor_override'] ?? null) === '1'
            || ($requestData['trust_rule42_supervisor_override'] ?? null) === 1;
        $reason = trim((string) ($requestData['trust_rule42_override_reason'] ?? ''));

        return [
            'type_id' => $typeId,
            'notice_given_date' => $notice !== '' ? $notice : null,
            'notes' => $notes !== '' ? $notes : null,
            'override' => $override,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array{type_id: int, notice_given_date: ?string, notes: ?string, override: bool, reason: string}  $payload
     */
    public static function validateAuthorityPayload(array $payload): void
    {
        if ($payload['type_id'] < 1) {
            throw new \RuntimeException('Rule 42: select a withdrawal authority type before posting a fee transfer from trust.');
        }

        $exists = DB::table('trust_withdrawal_authority_types')
            ->where('id', $payload['type_id'])
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new \RuntimeException('Rule 42: invalid or inactive withdrawal authority type.');
        }

        if ($payload['override'] && strlen($payload['reason']) < 10) {
            throw new \RuntimeException('Rule 42: supervisor override requires a reason (at least 10 characters).');
        }
    }

    public static function actorMayApplySupervisorOverride(?Staff $staff): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if ($staff->hasEffectiveSuperAdminPrivileges()) {
            return true;
        }

        return (bool) ($staff->trust_rule42_supervisor ?? false);
    }

    /**
     * Fee transfers without an invoice reference must document authority in notes.
     *
     * @param  array{type_id: int, notice_given_date: ?string, notes: ?string, override: bool, reason: string}  $payload
     */
    public static function assertNonInvoiceFeeTransferAuthority(array $payload): void
    {
        $notes = (string) ($payload['notes'] ?? '');
        if (strlen($notes) < 15) {
            throw new \RuntimeException(
                'Rule 42: fee transfers without an invoice reference require authority notes (at least 15 characters) explaining the basis for withdrawal.'
            );
        }
    }

    /**
     * @param  array{type_id: int, notice_given_date: ?string, notes: ?string, override: bool, reason: string}  $payload
     */
    public static function assertInvoiceEligibleForWithdrawal(
        string $invoiceNo,
        int $clientId,
        string $feeTransferTransDateDmY,
        ?Staff $actor,
        array $payload
    ): void {
        if (! self::isEnforcementActive()) {
            return;
        }

        if ($invoiceNo === '') {
            return;
        }

        $invoiceQuery = DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where('receipt_type', 3)
            ->where(function ($q) use ($invoiceNo) {
                $q->where('invoice_no', $invoiceNo)
                    ->orWhere('trans_no', $invoiceNo);
            });

        if (! $invoiceQuery->exists()) {
            throw new \RuntimeException('Rule 42: invoice not found for this fee transfer (check invoice reference).');
        }

        $hasVoid = (clone $invoiceQuery)->where(function ($q) {
            $q->where('void_invoice', 1);
        })->exists();

        if ($hasVoid && ! ($payload['override'] ?? false)) {
            throw new \RuntimeException('Rule 42: cannot withdraw trust money against a voided invoice.');
        }

        if ($hasVoid && $payload['override'] && ! self::actorMayApplySupervisorOverride($actor)) {
            throw new \RuntimeException('Rule 42: withdrawing against a voided invoice requires a supervisor or principal authorised for Rule 42 overrides.');
        }

        $hasDraft = (clone $invoiceQuery)->where('save_type', 'draft')->exists();

        if ($hasDraft && ! ($payload['override'] ?? false)) {
            throw new \RuntimeException(
                'Rule 42: invoice must be finalised (not draft) before trust money can be withdrawn for costs. Finalise the invoice or obtain a documented supervisor override.'
            );
        }

        if ($hasDraft && $payload['override'] && ! self::actorMayApplySupervisorOverride($actor)) {
            throw new \RuntimeException('Rule 42: overriding draft-invoice checks requires supervisor / principal Rule 42 authority.');
        }

        $minInvoiceDateStr = (clone $invoiceQuery)->selectRaw(
            "MIN(TO_DATE(trans_date, 'DD/MM/YYYY'))::text as d"
        )->value('d');

        if ($minInvoiceDateStr && $feeTransferTransDateDmY !== '') {
            try {
                $invoiceDate = Carbon::parse($minInvoiceDateStr)->startOfDay();
                $ftDate = Carbon::createFromFormat('d/m/Y', $feeTransferTransDateDmY)->startOfDay();
                if ($ftDate->lt($invoiceDate)) {
                    if (! ($payload['override'] ?? false)) {
                        throw new \RuntimeException(
                            'Rule 42: trust withdrawal date cannot be before the invoice date. Correct the transaction date or document a supervisor override.'
                        );
                    }
                    if (! self::actorMayApplySupervisorOverride($actor)) {
                        throw new \RuntimeException(
                            'Rule 42: backdating withdrawals before the invoice requires supervisor / principal Rule 42 authority.'
                        );
                    }
                }
            } catch (\Throwable) {
                // If parsing fails, skip date comparison rather than blocking unrelated saves.
            }
        }
    }

    /**
     * @param  array{type_id: int, notice_given_date: ?string, notes: ?string, override: bool, reason: string}  $payload
     */
    public static function recordAuthorityForNewFeeTransfer(
        int $feeTransferAccountClientReceiptId,
        int $clientId,
        ?string $invoiceNo,
        float $withdrawalAmount,
        array $payload,
        int $authorisedByStaffId
    ): void {
        if (! self::isEnforcementActive()) {
            return;
        }

        $noticeYmd = self::normalisedNoticeGivenDate($payload['notice_given_date'] ?? null);

        $authId = (int) DB::table('trust_withdrawal_authorities')->insertGetId([
            'account_client_receipt_id' => $feeTransferAccountClientReceiptId,
            'client_id' => $clientId,
            'invoice_no' => $invoiceNo !== null && $invoiceNo !== '' ? $invoiceNo : null,
            'withdrawal_amount' => round($withdrawalAmount, 2),
            'authority_type_id' => $payload['type_id'],
            'authorised_by_staff_id' => $authorisedByStaffId,
            'notice_given_date' => $noticeYmd,
            'authority_notes' => $payload['notes'],
            'supervisor_override' => $payload['override'],
            'override_reason' => $payload['override'] ? $payload['reason'] : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TrustLedgerAuditLogger::logForTable(
            'trust_withdrawal_authorities',
            'withdrawal_authority_created',
            $authId,
            'account_client_receipt_id',
            null,
            (string) $feeTransferAccountClientReceiptId,
            'Rule 42 authority for fee transfer id ' . $feeTransferAccountClientReceiptId
        );
    }

    public static function requestHasFeeTransferLines(array $requestData): bool
    {
        $types = $requestData['client_fund_ledger_type'] ?? [];
        if (! is_array($types)) {
            return false;
        }

        foreach ($types as $t) {
            if ($t === 'Fee Transfer') {
                return true;
            }
        }

        return false;
    }

    /**
     * Persist NULL or a Y-m-d date for the DB date column (accepts HTML date, ISO, or d/m/Y).
     */
    private static function normalisedNoticeGivenDate(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($raw))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
