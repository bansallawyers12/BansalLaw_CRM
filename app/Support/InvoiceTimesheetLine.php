<?php

namespace App\Support;

/**
 * Timesheet-style tax invoice lines: hours × rate (ex GST) + per-line GST.
 * Ledger withdraw_amount remains the GST-inclusive amount the client owes.
 */
class InvoiceTimesheetLine
{
    public const GST_RATE = 0.10;

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [
            'Principal',
            'Solicitor',
            'Associate',
            'Paralegal',
            'Law Clerk',
            'Graduate',
            'Legal Secretary',
            'Other',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function billingBases(): array
    {
        return [
            'hourly' => 'Hourly',
            'fixed' => 'Fixed',
        ];
    }

    public static function roundMoney(float $amount): float
    {
        return round($amount, 2);
    }

    public static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', '$', ' '], '', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Build stored line fields from a create/edit invoice request row.
     *
     * @param  array<string, mixed>  $requestData
     * @return array{
     *     billing_basis: string,
     *     hours: ?float,
     *     rate_ex_gst: ?float,
     *     amount_ex_gst: float,
     *     line_gst: float,
     *     withdraw_amount: float,
     *     gst_included: string,
     *     fee_earner_id: ?int,
     *     fee_earner_role: ?string
     * }
     */
    public static function fromRequest(array $requestData, int $index): array
    {
        $hours = self::nullableFloat($requestData['hours'][$index] ?? null);
        $rate = self::nullableFloat($requestData['rate_ex_gst'][$index] ?? null);
        $amountEx = self::nullableFloat($requestData['amount_ex_gst'][$index] ?? null);
        $lineGst = self::nullableFloat($requestData['line_gst'][$index] ?? null);
        $withdraw = self::nullableFloat($requestData['withdraw_amount'][$index] ?? null);
        $gstIncluded = trim((string) ($requestData['gst_included'][$index] ?? ''));
        $basis = strtolower(trim((string) ($requestData['billing_basis'][$index] ?? '')));

        if (! in_array($basis, ['hourly', 'fixed'], true)) {
            $basis = ($hours !== null && $hours > 0 && $rate !== null && $rate > 0) ? 'hourly' : 'fixed';
        }

        if ($basis === 'hourly' && $hours !== null && $rate !== null) {
            $amountEx = self::roundMoney($hours * $rate);
        }

        $hasTimesheetAmount = array_key_exists('amount_ex_gst', $requestData)
            && is_array($requestData['amount_ex_gst'])
            && array_key_exists($index, $requestData['amount_ex_gst'])
            && $requestData['amount_ex_gst'][$index] !== null
            && $requestData['amount_ex_gst'][$index] !== '';

        if (! $hasTimesheetAmount && $amountEx === null && $withdraw !== null) {
            if ($gstIncluded === 'Yes') {
                $inferredGst = self::roundMoney($withdraw / 11);
                $amountEx = self::roundMoney($withdraw - $inferredGst);
                $lineGst = $lineGst ?? $inferredGst;
            } else {
                $amountEx = $withdraw;
                $lineGst = $lineGst ?? 0.0;
            }
        }

        if ($amountEx === null) {
            $amountEx = 0.0;
        }

        if ($lineGst === null) {
            $lineGst = self::roundMoney($amountEx * self::GST_RATE);
        }

        $incl = self::roundMoney($amountEx + $lineGst);

        $feeEarnerId = $requestData['fee_earner_id'][$index] ?? null;
        if ($feeEarnerId === '' || $feeEarnerId === null) {
            $feeEarnerId = null;
        } else {
            $feeEarnerId = (int) $feeEarnerId;
            if ($feeEarnerId <= 0) {
                $feeEarnerId = null;
            }
        }

        $role = trim((string) ($requestData['fee_earner_role'][$index] ?? ''));

        return [
            'billing_basis' => $basis,
            'hours' => $hours,
            'rate_ex_gst' => $rate,
            'amount_ex_gst' => $amountEx,
            'line_gst' => $lineGst,
            'withdraw_amount' => $incl,
            'gst_included' => $lineGst > 0.00001 ? 'Yes' : 'No',
            'fee_earner_id' => $feeEarnerId,
            'fee_earner_role' => $role !== '' ? $role : null,
        ];
    }

    public static function displayFeeEarner(object $line): string
    {
        $name = '';
        if (isset($line->feeEarner) && $line->feeEarner) {
            $name = trim(($line->feeEarner->first_name ?? '').' '.($line->feeEarner->last_name ?? ''));
        }
        $role = trim((string) ($line->fee_earner_role ?? ''));
        if ($name !== '' && $role !== '') {
            return $name.' ('.$role.')';
        }

        return $name !== '' ? $name : $role;
    }

    /**
     * @param  iterable<int, object>  $lines
     * @return array{ex_gst: float, gst: float, incl_gst: float}
     */
    public static function pdfTotals(iterable $lines): array
    {
        $ex = 0.0;
        $gst = 0.0;
        $incl = 0.0;

        foreach ($lines as $line) {
            $sign = (($line->payment_type ?? '') === 'Discount') ? -1.0 : 1.0;
            $withdraw = (float) ($line->withdraw_amount ?? 0);
            $hasEx = $line->amount_ex_gst !== null && $line->amount_ex_gst !== '';

            if ($hasEx) {
                $ex += $sign * (float) $line->amount_ex_gst;
                $gst += $sign * (float) ($line->line_gst ?? 0);
                $incl += $sign * $withdraw;
                continue;
            }

            if (($line->gst_included ?? '') === 'Yes') {
                $lineGst = self::roundMoney($withdraw / 11);
                $ex += $sign * ($withdraw - $lineGst);
                $gst += $sign * $lineGst;
            } else {
                $ex += $sign * $withdraw;
            }
            $incl += $sign * $withdraw;
        }

        return [
            'ex_gst' => self::roundMoney($ex),
            'gst' => self::roundMoney($gst),
            'incl_gst' => self::roundMoney($incl),
        ];
    }

    /**
     * @return array{amount_ex_gst: float, line_gst: float, hrs: string, rate: string}
     */
    public static function displayAmounts(object $line): array
    {
        $hours = $line->hours ?? null;
        $rate = $line->rate_ex_gst ?? null;
        $amountEx = $line->amount_ex_gst ?? null;
        $lineGst = $line->line_gst ?? null;
        $withdraw = (float) ($line->withdraw_amount ?? 0);

        if ($amountEx === null || $amountEx === '') {
            if (($line->gst_included ?? '') === 'Yes') {
                $lineGst = self::roundMoney($withdraw / 11);
                $amountEx = self::roundMoney($withdraw - $lineGst);
            } else {
                $amountEx = $withdraw;
                $lineGst = 0.0;
            }
        }

        return [
            'amount_ex_gst' => (float) $amountEx,
            'line_gst' => (float) ($lineGst ?? 0),
            'hrs' => ($hours === null || $hours === '') ? '—' : rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.'),
            'rate' => ($rate === null || $rate === '') ? '—' : number_format((float) $rate, 2),
        ];
    }
}
