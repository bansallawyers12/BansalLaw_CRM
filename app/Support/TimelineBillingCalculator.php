<?php

namespace App\Support;

/**
 * Timeline / activity billing totals using the firm statement layout rules:
 * - Professional fees are entered GST-inclusive (unit rate × units).
 * - Fee GST = incl / 11; fee net = incl − GST.
 * - Disbursements store net + GST separately (GST may not be flat 10%).
 * - Summary:
 *   Total Fees and Disbursements (Net) = fees net + disbursements net
 *   GST Included = fees GST + disbursements GST
 *   Total Amount Due = net + GST (= fees incl + disbursements incl)
 */
class TimelineBillingCalculator
{
    public static function unitRateInclGst(): float
    {
        return round((float) config('crm.timeline_billing.unit_rate_incl_gst', 55.0), 2);
    }

    public static function gstRate(): float
    {
        return (float) config('crm.timeline_billing.gst_rate', 0.10);
    }

    /**
     * GST component of a GST-inclusive amount (Australian tax invoice rule: / 11).
     */
    public static function gstFromInclusive(float $amountInclGst): float
    {
        return round($amountInclGst / 11, 2);
    }

    public static function netFromInclusive(float $amountInclGst): float
    {
        return round($amountInclGst - self::gstFromInclusive($amountInclGst), 2);
    }

    /**
     * @param  array<int, array{units?: int|float, amount_incl_gst?: float|int|string}>  $feeLines
     * @param  array<int, array{net?: float|int|string|null, gst?: float|int|string|null, amount_incl_gst?: float|int|string|null}>  $disbursements
     * @return array{
     *     unit_rate_incl_gst: float,
     *     professional_fees: array{incl_gst: float, net: float, gst: float},
     *     disbursements: array{net: float, gst: float, incl_gst: float},
     *     total_fees_and_disbursements_net: float,
     *     gst_included: float,
     *     total_amount_due: float
     * }
     */
    public static function calculate(array $feeLines, array $disbursements = []): array
    {
        $unitRate = self::unitRateInclGst();
        $feesIncl = 0.0;

        foreach ($feeLines as $line) {
            if (array_key_exists('amount_incl_gst', $line) && $line['amount_incl_gst'] !== null && $line['amount_incl_gst'] !== '') {
                $feesIncl += (float) $line['amount_incl_gst'];
            } else {
                $feesIncl += ((float) ($line['units'] ?? 0)) * $unitRate;
            }
        }

        $feesIncl = round($feesIncl, 2);
        $feesGst = self::gstFromInclusive($feesIncl);
        $feesNet = self::netFromInclusive($feesIncl);

        $disbNet = 0.0;
        $disbGst = 0.0;
        $disbIncl = 0.0;

        foreach ($disbursements as $row) {
            $normalized = self::normalizeDisbursement($row);
            $disbNet += $normalized['net'];
            $disbGst += $normalized['gst'];
            $disbIncl += $normalized['incl_gst'];
        }

        $disbNet = round($disbNet, 2);
        $disbGst = round($disbGst, 2);
        $disbIncl = round($disbIncl, 2);

        $totalNet = round($feesNet + $disbNet, 2);
        $totalGst = round($feesGst + $disbGst, 2);
        $totalDue = round($feesIncl + $disbIncl, 2);

        return [
            'unit_rate_incl_gst' => $unitRate,
            'professional_fees' => [
                'incl_gst' => $feesIncl,
                'net' => $feesNet,
                'gst' => $feesGst,
            ],
            'disbursements' => [
                'net' => $disbNet,
                'gst' => $disbGst,
                'incl_gst' => $disbIncl,
            ],
            'total_fees_and_disbursements_net' => $totalNet,
            'gst_included' => $totalGst,
            'total_amount_due' => $totalDue,
        ];
    }

    /**
     * @param  array{net?: float|int|string|null, gst?: float|int|string|null, amount_incl_gst?: float|int|string|null}  $row
     * @return array{net: float, gst: float, incl_gst: float}
     */
    public static function normalizeDisbursement(array $row): array
    {
        $hasNet = array_key_exists('net', $row) && $row['net'] !== null && $row['net'] !== '';
        $hasGst = array_key_exists('gst', $row) && $row['gst'] !== null && $row['gst'] !== '';
        $hasIncl = array_key_exists('amount_incl_gst', $row) && $row['amount_incl_gst'] !== null && $row['amount_incl_gst'] !== '';

        $net = $hasNet ? (float) $row['net'] : null;
        $gst = $hasGst ? (float) $row['gst'] : null;
        $incl = $hasIncl ? (float) $row['amount_incl_gst'] : null;

        if ($net !== null && $gst !== null && $incl === null) {
            $incl = round($net + $gst, 2);
        } elseif ($net !== null && $incl !== null && $gst === null) {
            $gst = round($incl - $net, 2);
        } elseif ($gst !== null && $incl !== null && $net === null) {
            $net = round($incl - $gst, 2);
        } elseif ($net !== null && $gst === null && $incl === null) {
            $gst = round($net * self::gstRate(), 2);
            $incl = round($net + $gst, 2);
        } elseif ($incl !== null && $net === null && $gst === null) {
            $gst = self::gstFromInclusive($incl);
            $net = self::netFromInclusive($incl);
        } else {
            $net = $net ?? 0.0;
            $gst = $gst ?? 0.0;
            $incl = $incl ?? round($net + $gst, 2);
        }

        return [
            'net' => round((float) $net, 2),
            'gst' => round((float) $gst, 2),
            'incl_gst' => round((float) $incl, 2),
        ];
    }
}
