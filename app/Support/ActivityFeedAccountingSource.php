<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves Timeline accounting activities (invoice / office receipt / trust ledger)
 * to the Account tab row so the feed can show complete details and deep-link to source.
 */
class ActivityFeedAccountingSource
{
    public const KIND_INVOICE = 'invoice';

    public const KIND_OFFICE_RECEIPT = 'office_receipt';

    public const KIND_TRUST_LEDGER = 'trust_ledger';

    /**
     * @param  Collection<int, object>  $activities  ActivitiesLog models (or objects with subject)
     * @return array<int, array<string, mixed>> keyed by activity id
     */
    public static function mapForActivities(int $clientId, Collection $activities): array
    {
        $parsed = [];
        foreach ($activities as $activity) {
            $id = (int) ($activity->id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $meta = self::parseSubject((string) ($activity->subject ?? ''));
            if ($meta === null) {
                continue;
            }
            $parsed[$id] = $meta;
        }

        if ($parsed === []) {
            return [];
        }

        $invoiceRefs = [];
        $officeRefs = [];
        $trustRefs = [];
        foreach ($parsed as $meta) {
            if ($meta['kind'] === self::KIND_INVOICE) {
                $invoiceRefs[] = $meta['reference'];
            } elseif ($meta['kind'] === self::KIND_OFFICE_RECEIPT) {
                $officeRefs[] = $meta['reference'];
            } elseif ($meta['kind'] === self::KIND_TRUST_LEDGER) {
                $trustRefs[] = $meta['reference'];
            }
        }

        $invoicesByRef = self::latestRowsByTransNo($clientId, 3, array_values(array_unique($invoiceRefs)));
        $officeByRef = self::latestRowsByTransNo($clientId, 2, array_values(array_unique($officeRefs)));
        $trustByRef = self::latestRowsByTransNo($clientId, 1, array_values(array_unique($trustRefs)));

        $out = [];
        foreach ($parsed as $activityId => $meta) {
            $row = null;
            if ($meta['kind'] === self::KIND_INVOICE) {
                $row = $invoicesByRef[$meta['reference']] ?? null;
            } elseif ($meta['kind'] === self::KIND_OFFICE_RECEIPT) {
                $row = $officeByRef[$meta['reference']] ?? null;
            } elseif ($meta['kind'] === self::KIND_TRUST_LEDGER) {
                $row = $trustByRef[$meta['reference']] ?? null;
            }

            $out[$activityId] = self::buildPayload($meta, $row);
        }

        return $out;
    }

    /**
     * @return array{kind: string, reference: string}|null
     */
    public static function parseSubject(string $subject): ?array
    {
        if ($subject === '' || ! preg_match('/Reference\s*no-?\s*([A-Za-z0-9\-_\/]+)/i', $subject, $m)) {
            return null;
        }

        $reference = trim($m[1]);
        if ($reference === '') {
            return null;
        }

        $lower = strtolower($subject);
        if (str_contains($lower, 'invoice')) {
            return ['kind' => self::KIND_INVOICE, 'reference' => $reference];
        }
        if (str_contains($lower, 'office receipt')) {
            return ['kind' => self::KIND_OFFICE_RECEIPT, 'reference' => $reference];
        }
        if (str_contains($lower, 'client funds ledger') || str_contains($lower, 'trust')) {
            return ['kind' => self::KIND_TRUST_LEDGER, 'reference' => $reference];
        }
        if (str_contains($lower, 'receipt') || str_contains($lower, 'ledger') || str_contains($lower, 'payment')) {
            // Generic accounting — still deep-link to Account tab by reference search.
            return ['kind' => self::KIND_INVOICE, 'reference' => $reference];
        }

        return null;
    }

    /**
     * @param  array{kind: string, reference: string}  $meta
     * @return array<string, mixed>
     */
    private static function buildPayload(array $meta, ?object $row): array
    {
        $kind = $meta['kind'];
        $reference = $meta['reference'];

        $payload = [
            'kind' => $kind,
            'reference' => $reference,
            'tab' => 'account',
            'found' => $row !== null,
            'row_id' => $row ? (int) $row->id : null,
            'receipt_id' => $row ? (int) ($row->receipt_id ?? $row->id) : null,
            'details' => [
                'reference' => $reference,
                'date' => $row ? (string) ($row->trans_date ?? '') : '',
                'description' => $row ? (string) ($row->description ?? '') : '',
                'amount' => $row ? self::formatAmountForKind($kind, $row) : '',
                'status' => $row && $kind === self::KIND_INVOICE ? self::invoiceStatusLabel($row) : '',
                'status_class' => $row && $kind === self::KIND_INVOICE ? self::invoiceStatusClass($row) : '',
                'kind_label' => match ($kind) {
                    self::KIND_INVOICE => 'Invoice',
                    self::KIND_OFFICE_RECEIPT => 'Office receipt',
                    self::KIND_TRUST_LEDGER => 'Trust ledger entry',
                    default => 'Accounting entry',
                },
            ],
        ];

        return $payload;
    }

    /**
     * @param  list<string>  $refs
     * @return array<string, object> keyed by trans_no
     */
    private static function latestRowsByTransNo(int $clientId, int $receiptType, array $refs): array
    {
        if ($refs === []) {
            return [];
        }

        $rows = DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where('receipt_type', $receiptType)
            ->whereIn('trans_no', $refs)
            ->orderByDesc('id')
            ->get();

        $byRef = [];
        foreach ($rows as $row) {
            $key = (string) ($row->trans_no ?? '');
            if ($key === '' || isset($byRef[$key])) {
                continue;
            }
            $byRef[$key] = $row;
        }

        return $byRef;
    }

    private static function formatAmountForKind(string $kind, object $row): string
    {
        if ($kind === self::KIND_INVOICE) {
            $amount = abs((float) ($row->withdraw_amount ?? 0));
        } elseif ($kind === self::KIND_OFFICE_RECEIPT) {
            $amount = abs((float) ($row->deposit_amount ?? 0));
        } else {
            $deposit = (float) ($row->deposit_amount ?? 0);
            $withdraw = (float) ($row->withdraw_amount ?? 0);
            $amount = $deposit > 0 ? $deposit : abs($withdraw);
        }

        if ($amount <= 0 && isset($row->balance_amount)) {
            $amount = abs((float) $row->balance_amount);
        }

        return '$ '.number_format($amount, 2);
    }

    private static function invoiceStatusLabel(object $row): string
    {
        if (($row->save_type ?? '') === 'draft') {
            return 'Draft';
        }
        if (($row->payment_type ?? '') === 'Discount') {
            return 'Discount';
        }
        if ((int) ($row->void_invoice ?? 0) === 1) {
            return 'Void';
        }

        return match ((int) ($row->invoice_status ?? 0)) {
            1 => 'Paid',
            2 => 'Partial',
            3 => 'Void',
            default => 'Unpaid',
        };
    }

    private static function invoiceStatusClass(object $row): string
    {
        if (($row->save_type ?? '') === 'draft') {
            return 'status-draft';
        }

        return match ((int) ($row->invoice_status ?? 0)) {
            1 => 'status-paid',
            2 => 'status-partial',
            3 => 'status-void',
            default => 'status-unpaid',
        };
    }
}
