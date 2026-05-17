<?php

namespace App\Services\TrustAccounting;

use App\Models\TrustBankStatementLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: bank statement lines and manual matching to trust ledger rows (read-only on ledger).
 */
class TrustBankReconciliationService
{
    public static function createStatementLine(
        int $trustBankAccountId,
        string $valueDateYmd,
        float $amount,
        ?string $narrative,
        ?string $bankReference
    ): int {
        if (! Schema::hasTable('trust_bank_statement_lines')) {
            throw new \RuntimeException('Bank reconciliation is not available.');
        }

        if (self::amountsEqual($amount, 0.0)) {
            throw new \RuntimeException('Amount cannot be zero.');
        }

        $id = (int) DB::table('trust_bank_statement_lines')->insertGetId([
            'trust_bank_account_id' => $trustBankAccountId,
            'value_date' => $valueDateYmd,
            'amount' => round($amount, 2),
            'narrative' => $narrative,
            'bank_reference' => $bankReference,
            'matched_account_client_receipt_id' => null,
            'matched_at' => null,
            'matched_by_staff_id' => null,
            'match_notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TrustLedgerAuditLogger::logForTable(
            'trust_bank_statement_lines',
            'statement_line_created',
            $id,
            null,
            null,
            null,
            $valueDateYmd . ' | ' . number_format($amount, 2, '.', '')
        );

        return $id;
    }

    public static function deleteStatementLine(int $statementLineId): void
    {
        if (! Schema::hasTable('trust_bank_statement_lines')) {
            throw new \RuntimeException('Bank reconciliation is not available.');
        }

        $line = TrustBankStatementLine::query()->find($statementLineId);
        if (! $line) {
            throw new \RuntimeException('Bank statement line not found.');
        }

        if ($line->matched_account_client_receipt_id) {
            throw new \RuntimeException('Unmatch this line before deleting it.');
        }

        DB::table('trust_bank_statement_lines')->where('id', $line->id)->delete();

        TrustLedgerAuditLogger::logForTable(
            'trust_bank_statement_lines',
            'statement_line_deleted',
            (int) $line->id,
            null,
            null,
            null,
            null
        );
    }

    public static function amountsEqual(float $a, float $b): bool
    {
        return round($a - $b, 2) === 0.0;
    }

    public static function assertReceiptEligibleForMatching(object $receipt): void
    {
        if ((int) ($receipt->receipt_type ?? 0) !== 1) {
            throw new \RuntimeException('Only trust ledger rows can be matched.');
        }

        if (isset($receipt->void_fee_transfer) && (int) $receipt->void_fee_transfer === 1) {
            throw new \RuntimeException('Voided fee-transfer rows cannot be matched.');
        }

        if (Schema::hasColumn('account_client_receipts', 'trust_voided_at')
            && ($receipt->trust_voided_at ?? null) !== null) {
            throw new \RuntimeException('Voided trust rows cannot be matched.');
        }
    }

    public static function assertAmountCompatibleWithReceipt(float $statementAmount, object $receipt): void
    {
        $deposit = (float) ($receipt->deposit_amount ?? 0);
        $withdraw = (float) ($receipt->withdraw_amount ?? 0);

        if ($statementAmount > 0) {
            if ($deposit <= 0 || ! self::amountsEqual($statementAmount, $deposit)) {
                throw new \RuntimeException(
                    'Bank credit lines must match a ledger row with the same deposit amount.'
                );
            }

            return;
        }

        if ($statementAmount < 0) {
            if ($withdraw <= 0 || ! self::amountsEqual(abs($statementAmount), $withdraw)) {
                throw new \RuntimeException(
                    'Bank debit lines must match a ledger row with the same payment (withdrawal) amount.'
                );
            }

            return;
        }

        throw new \RuntimeException('Statement line amount cannot be zero.');
    }

    public static function receiptAlreadyMatched(int $receiptId): bool
    {
        if (! Schema::hasTable('trust_bank_statement_lines')) {
            return false;
        }

        return DB::table('trust_bank_statement_lines')
            ->where('matched_account_client_receipt_id', $receiptId)
            ->exists();
    }

    public static function matchLineToReceipt(
        int $statementLineId,
        int $receiptId,
        int $staffId,
        ?string $matchNotes = null
    ): void {
        if (! Schema::hasTable('trust_bank_statement_lines')) {
            throw new \RuntimeException('Bank reconciliation is not available.');
        }

        DB::transaction(function () use ($statementLineId, $receiptId, $staffId, $matchNotes) {
            $line = TrustBankStatementLine::query()->whereKey($statementLineId)->lockForUpdate()->first();
            if (! $line) {
                throw new \RuntimeException('Bank statement line not found.');
            }

            if ($line->matched_account_client_receipt_id) {
                throw new \RuntimeException('This bank line is already matched.');
            }

            $receipt = DB::table('account_client_receipts')->where('id', $receiptId)->lockForUpdate()->first();
            if (! $receipt) {
                throw new \RuntimeException('Ledger row not found.');
            }

            self::assertReceiptEligibleForMatching($receipt);

            if (self::receiptAlreadyMatched($receiptId)) {
                throw new \RuntimeException('This ledger row is already matched to another bank line.');
            }

            self::assertAmountCompatibleWithReceipt((float) $line->amount, $receipt);

            DB::table('trust_bank_statement_lines')->where('id', $line->id)->update([
                'matched_account_client_receipt_id' => $receiptId,
                'matched_at' => now(),
                'matched_by_staff_id' => $staffId,
                'match_notes' => $matchNotes,
                'updated_at' => now(),
            ]);

            TrustLedgerAuditLogger::logForTable(
                'trust_bank_statement_lines',
                'bank_line_matched',
                (int) $line->id,
                'matched_account_client_receipt_id',
                null,
                (string) $receiptId,
                'Trust ledger row TR match: receipt id ' . $receiptId
            );
        });
    }

    public static function unmatchLine(int $statementLineId, int $staffId): void
    {
        unset($staffId);

        if (! Schema::hasTable('trust_bank_statement_lines')) {
            throw new \RuntimeException('Bank reconciliation is not available.');
        }

        DB::transaction(function () use ($statementLineId) {
            $line = TrustBankStatementLine::query()->whereKey($statementLineId)->lockForUpdate()->first();
            if (! $line) {
                throw new \RuntimeException('Bank statement line not found.');
            }

            if (! $line->matched_account_client_receipt_id) {
                throw new \RuntimeException('This bank line is not matched.');
            }

            $prevReceiptId = (int) $line->matched_account_client_receipt_id;

            DB::table('trust_bank_statement_lines')->where('id', $line->id)->update([
                'matched_account_client_receipt_id' => null,
                'matched_at' => null,
                'matched_by_staff_id' => null,
                'match_notes' => null,
                'updated_at' => now(),
            ]);

            TrustLedgerAuditLogger::logForTable(
                'trust_bank_statement_lines',
                'bank_line_unmatched',
                (int) $line->id,
                'matched_account_client_receipt_id',
                (string) $prevReceiptId,
                null,
                null
            );
        });
    }
}
