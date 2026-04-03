<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Financial Statistics Service
 * 
 * Centralized service for calculating financial statistics and analytics
 * across receipts, invoices, and office transactions.
 */
class FinancialStatsService
{
    /**
     * Get comprehensive dashboard statistics
     * 
     * @param array $options Filter options (date_range, client_id, matter_id, receipt_type)
     * @return array
     */
    public function getDashboardStats($options = [])
    {
        $startDate = $options['start_date'] ?? Carbon::now()->startOfMonth();
        $endDate = $options['end_date'] ?? Carbon::now()->endOfMonth();
        $receiptType = $options['receipt_type'] ?? null; // null = all, 1-4 = specific type
        
        return [
            'monthly_stats' => $this->getMonthlyStats($startDate, $endDate, $receiptType),
            'receipt_stats' => $this->getReceiptStats($startDate, $endDate, $receiptType),
            'invoice_stats' => $this->getInvoiceStats($startDate, $endDate, $receiptType),
            'trend_data' => $this->getTrendData($receiptType),
            'top_clients' => $this->getTopClients($startDate, $endDate, 5, $receiptType),
            'allocation_metrics' => $this->getAllocationMetrics($startDate, $endDate, $receiptType),
            'receipt_type' => $receiptType,
        ];
    }

    /**
     * Get monthly statistics for current month
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int|null $receiptType Filter by receipt type (null = all)
     * @return array
     */
    public function getMonthlyStats($startDate, $endDate, $receiptType = null)
    {
        // Helper function to apply receipt_type filter
        $applyTypeFilter = function($query) use ($receiptType) {
            if ($receiptType !== null) {
                $query->where('receipt_type', $receiptType);
            }
            return $query;
        };

        // Convert dates from Y-m-d to dd/mm/yyyy format for VARCHAR comparison
        // Database stores dates as dd/mm/yyyy in VARCHAR field
        $startDateStr = $startDate->format('d/m/Y');
        $endDateStr = $endDate->format('d/m/Y');
        
        // Helper function to convert dd/mm/yyyy VARCHAR to comparable format
        $applyDateFilter = function($query, $start, $end) {
            // Since trans_date is VARCHAR in dd/mm/yyyy format, we need to convert for PostgreSQL
            // PostgreSQL: TO_DATE(trans_date, 'DD/MM/YYYY')
            // CRITICAL: Filter NULL values first - TO_DATE() fails on NULL values
            return $query->whereNotNull('trans_date')
                ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$start, $end]);
        };

        // Total deposits this month (Client Receipts - receipt_type 1)
        $totalDeposits = $receiptType === null || $receiptType == 1
            ? $applyDateFilter($applyTypeFilter(DB::table('account_client_receipts')->where('receipt_type', 1)), $startDateStr, $endDateStr)
                ->sum('deposit_amount')
            : 0;

        // Total fee transfers this month (only for Client Receipts)
        $totalFeeTransfers = $receiptType === null || $receiptType == 1
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 1)
                ->where('client_fund_ledger_type', 'Fee Transfer')
                ->where(function($q) {
                    $q->whereNull('void_fee_transfer')
                      ->orWhere('void_fee_transfer', '!=', 1);
                }), $startDateStr, $endDateStr)
                ->sum('withdraw_amount')
            : 0;

        // Total office receipts this month (receipt_type 2)
        $totalOfficeReceipts = $receiptType === null || $receiptType == 2
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 2), $startDateStr, $endDateStr)
                ->sum('deposit_amount')
            : 0;

        // Total invoices issued this month (receipt_type 3)
        $totalInvoicesIssued = $receiptType === null || $receiptType == 3
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 3)
                ->where(function($q) {
                    $q->whereNull('void_invoice')
                      ->orWhere('void_invoice', '!=', 1);
                }), $startDateStr, $endDateStr)
                ->sum('withdraw_amount')
            : 0;

        // Total journal receipts this month (receipt_type 4)
        $totalJournalReceipts = $receiptType === null || $receiptType == 4
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 4), $startDateStr, $endDateStr)
                ->sum('deposit_amount')
            : 0;

        // Count of transactions
        $depositCount = $receiptType === null || $receiptType == 1
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 1), $startDateStr, $endDateStr)
                ->count()
            : 0;

        $officeReceiptCount = $receiptType === null || $receiptType == 2
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 2), $startDateStr, $endDateStr)
                ->count()
            : 0;

        $invoiceCount = $receiptType === null || $receiptType == 3
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 3)
                ->where(function($q) {
                    $q->whereNull('void_invoice')
                      ->orWhere('void_invoice', '!=', 1);
                }), $startDateStr, $endDateStr)
                ->selectRaw('COUNT(DISTINCT receipt_id) as count')
                ->value('count') ?? 0
            : 0;

        $journalReceiptCount = $receiptType === null || $receiptType == 4
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 4), $startDateStr, $endDateStr)
                ->count()
            : 0;

        // Calculate trends (compare with previous period)
        $previousPeriodDays = $startDate->diffInDays($endDate);
        $previousStartDate = $startDate->copy()->subDays($previousPeriodDays);
        $previousEndDate = $startDate->copy()->subDay();
        
        $previousStartDateStr = $previousStartDate->format('d/m/Y');
        $previousEndDateStr = $previousEndDate->format('d/m/Y');

        $previousDeposits = $receiptType === null || $receiptType == 1
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 1), $previousStartDateStr, $previousEndDateStr)
                ->sum('deposit_amount')
            : 0;

        $previousOfficeReceipts = $receiptType === null || $receiptType == 2
            ? $applyDateFilter(DB::table('account_client_receipts')
                ->where('receipt_type', 2), $previousStartDateStr, $previousEndDateStr)
                ->sum('deposit_amount')
            : 0;

        return [
            'total_deposits' => $totalDeposits,
            'total_fee_transfers' => $totalFeeTransfers,
            'total_office_receipts' => $totalOfficeReceipts,
            'total_invoices_issued' => $totalInvoicesIssued,
            'total_journal_receipts' => $totalJournalReceipts,
            'deposit_count' => $depositCount,
            'office_receipt_count' => $officeReceiptCount,
            'invoice_count' => $invoiceCount,
            'journal_receipt_count' => $journalReceiptCount,
            'trends' => [
                'deposits' => $this->calculateTrendPercentage($previousDeposits, $totalDeposits),
                'office_receipts' => $this->calculateTrendPercentage($previousOfficeReceipts, $totalOfficeReceipts),
            ],
        ];
    }

    /**
     * Get receipt-related statistics
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int|null $receiptType Filter by receipt type (null = all)
     * @return array
     */
    public function getReceiptStats($startDate, $endDate, $receiptType = null)
    {
        // Only calculate for Client Receipts (type 1) or when showing all
        if ($receiptType !== null && $receiptType != 1) {
            return [
                'unallocated_count' => 0,
                'allocated_count' => 0,
                'total_client_receipts' => 0,
                'allocation_percentage' => 0,
            ];
        }

        // Convert dates to dd/mm/yyyy format
        $startDateStr = $startDate->format('d/m/Y');
        $endDateStr = $endDate->format('d/m/Y');

        // Unallocated receipts count (within date range)
        $unallocatedCount = DB::table('account_client_receipts')
            ->where('receipt_type', 1)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->where(function($q) {
                $q->whereNull('validate_receipt')
                  ->orWhere('validate_receipt', 0);
            })
            ->where(function($q) {
                $q->whereNull('void_fee_transfer')
                  ->orWhere('void_fee_transfer', '!=', 1);
            })
            ->count();

        // Total allocated receipts (within date range)
        $allocatedCount = DB::table('account_client_receipts')
            ->where('receipt_type', 1)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->where('validate_receipt', 1)
            ->count();

        // Total client receipts (within date range)
        $totalClientReceipts = DB::table('account_client_receipts')
            ->where('receipt_type', 1)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->count();

        // Allocation percentage
        $allocationPercentage = $totalClientReceipts > 0 
            ? round(($allocatedCount / $totalClientReceipts) * 100, 1) 
            : 0;

        return [
            'unallocated_count' => $unallocatedCount,
            'allocated_count' => $allocatedCount,
            'total_client_receipts' => $totalClientReceipts,
            'allocation_percentage' => $allocationPercentage,
        ];
    }

    /**
     * Get invoice-related statistics
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int|null $receiptType Filter by receipt type (null = all)
     * @return array
     */
    public function getInvoiceStats($startDate, $endDate, $receiptType = null)
    {
        // Only calculate for Invoices (type 3) or when showing all
        if ($receiptType !== null && $receiptType != 3) {
            return [
                'total_invoices' => 0,
                'unpaid_invoices' => 0,
                'overdue_invoices' => 0,
                'paid_invoices' => 0,
                'unpaid_amount' => 0,
                'payment_rate' => 0,
            ];
        }

        // Convert dates to dd/mm/yyyy format
        $startDateStr = $startDate->format('d/m/Y');
        $endDateStr = $endDate->format('d/m/Y');
        $todayStr = Carbon::today()->format('d/m/Y');

        // Total invoices (by receipt_id, not individual records) within date range
        $totalInvoices = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->where(function($q) {
                $q->whereNull('void_invoice')
                  ->orWhere('void_invoice', '!=', 1);
            })
            ->selectRaw('COUNT(DISTINCT receipt_id) as count')
            ->value('count') ?? 0;

        // Unpaid invoices (invoice_status != 2) within date range
        $unpaidInvoices = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->where(function($q) {
                $q->whereNull('void_invoice')
                  ->orWhere('void_invoice', '!=', 1);
            })
            ->where(function($q) {
                $q->where('invoice_status', '!=', 2)
                  ->orWhereNull('invoice_status');
            })
            ->selectRaw('COUNT(DISTINCT receipt_id) as count')
            ->value('count') ?? 0;

        // Overdue invoices (trans_date in past and not fully paid) within date range
        $overdueInvoices = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->where(function($q) {
                $q->whereNull('void_invoice')
                  ->orWhere('void_invoice', '!=', 1);
            })
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') < TO_DATE(?, 'DD/MM/YYYY')", [$todayStr])
            ->where(function($q) {
                $q->where('invoice_status', '!=', 2)
                  ->orWhereNull('invoice_status');
            })
            ->selectRaw('COUNT(DISTINCT receipt_id) as count')
            ->value('count') ?? 0;

        // Paid invoices within date range
        $paidInvoices = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->where('invoice_status', 2)
            ->where(function($q) {
                $q->whereNull('void_invoice')
                  ->orWhere('void_invoice', '!=', 1);
            })
            ->selectRaw('COUNT(DISTINCT receipt_id) as count')
            ->value('count') ?? 0;

        // Total unpaid amount for invoices within date range
        $unpaidAmount = DB::table(DB::raw('(SELECT receipt_id, MAX(balance_amount) as balance_amount 
            FROM account_client_receipts 
            WHERE receipt_type = 3 
            AND trans_date IS NOT NULL
            AND TO_DATE(trans_date, \'DD/MM/YYYY\') BETWEEN TO_DATE(\'' . $startDateStr . '\', \'DD/MM/YYYY\') AND TO_DATE(\'' . $endDateStr . '\', \'DD/MM/YYYY\')
            AND (void_invoice IS NULL OR void_invoice != 1)
            AND (invoice_status != 2 OR invoice_status IS NULL)
            GROUP BY receipt_id) as unpaid_invoices'))
            ->selectRaw('COALESCE(SUM(balance_amount), 0) as total')
            ->value('total') ?? 0;

        return [
            'total_invoices' => $totalInvoices,
            'unpaid_invoices' => $unpaidInvoices,
            'overdue_invoices' => $overdueInvoices,
            'paid_invoices' => $paidInvoices,
            'unpaid_amount' => $unpaidAmount,
            'payment_rate' => $totalInvoices > 0 
                ? round(($paidInvoices / $totalInvoices) * 100, 1) 
                : 0,
        ];
    }

    /**
     * Get allocation metrics (average days to allocate)
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int|null $receiptType Filter by receipt type (null = all)
     * @return array
     */
    public function getAllocationMetrics($startDate, $endDate, $receiptType = null)
    {
        // Only calculate for Client Receipts (type 1) or when showing all
        if ($receiptType !== null && $receiptType != 1) {
            return [
                'average_days_to_allocate' => 0,
                'total_validated_receipts' => 0,
                'old_unallocated_count' => 0,
            ];
        }

        // Convert dates to dd/mm/yyyy format
        $startDateStr = $startDate->format('d/m/Y');
        $endDateStr = $endDate->format('d/m/Y');
        $thirtyDaysAgoStr = Carbon::now()->subDays(30)->format('d/m/Y');

        // Get receipts that have been validated within date range
        $validatedReceipts = DB::table('account_client_receipts')
            ->where('receipt_type', 1)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->where('validate_receipt', 1)
            ->whereNotNull('created_at')
            ->whereNotNull('updated_at')
            ->select('created_at', 'updated_at')
            ->get();

        $totalDays = 0;
        $count = 0;

        foreach ($validatedReceipts as $receipt) {
            $createdAt = Carbon::parse($receipt->created_at);
            $updatedAt = Carbon::parse($receipt->updated_at);
            $daysDiff = $createdAt->diffInDays($updatedAt);
            
            // Only count if there's a meaningful difference (exclude same-day allocations from average)
            if ($daysDiff > 0) {
                $totalDays += $daysDiff;
                $count++;
            }
        }

        $averageDays = $count > 0 ? round($totalDays / $count, 1) : 0;

        // Get receipts older than 30 days that are unallocated (within date range)
        $oldUnallocated = DB::table('account_client_receipts')
            ->where('receipt_type', 1)
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->where(function($q) {
                $q->whereNull('validate_receipt')
                  ->orWhere('validate_receipt', 0);
            })
            ->where('created_at', '<', Carbon::now()->subDays(30))
            ->count();

        return [
            'average_days_to_allocate' => $averageDays,
            'total_validated_receipts' => $validatedReceipts->count(),
            'old_unallocated_count' => $oldUnallocated,
        ];
    }

    /**
     * Get trend data for charts (last 6 months)
     * 
     * @param int|null $receiptType Filter by receipt type (null = all)
     * @return array
     */
    public function getTrendData($receiptType = null)
    {
        $months = [];
        $deposits = [];
        $officeReceipts = [];
        $invoices = [];
        $journalReceipts = [];

        for ($i = 5; $i >= 0; $i--) {
            $startDate = Carbon::now()->subMonths($i)->startOfMonth();
            $endDate = Carbon::now()->subMonths($i)->endOfMonth();
            
            $startDateStr = $startDate->format('d/m/Y');
            $endDateStr = $endDate->format('d/m/Y');
            
            $months[] = $startDate->format('M Y');
            
            // Deposits (Client Receipts - type 1)
            $deposits[] = ($receiptType === null || $receiptType == 1)
                ? DB::table('account_client_receipts')
                    ->where('receipt_type', 1)
                    ->whereNotNull('trans_date')
                    ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
                    ->sum('deposit_amount')
                : 0;
            
            // Office Receipts (type 2)
            $officeReceipts[] = ($receiptType === null || $receiptType == 2)
                ? DB::table('account_client_receipts')
                    ->where('receipt_type', 2)
                    ->whereNotNull('trans_date')
                    ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
                    ->sum('deposit_amount')
                : 0;
            
            // Invoices (type 3)
            $invoices[] = ($receiptType === null || $receiptType == 3)
                ? DB::table('account_client_receipts')
                    ->where('receipt_type', 3)
                    ->whereNotNull('trans_date')
                    ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
                    ->where(function($q) {
                        $q->whereNull('void_invoice')
                          ->orWhere('void_invoice', '!=', 1);
                    })
                    ->sum('withdraw_amount')
                : 0;
            
            // Journal Receipts (type 4)
            $journalReceipts[] = ($receiptType === null || $receiptType == 4)
                ? DB::table('account_client_receipts')
                    ->where('receipt_type', 4)
                    ->whereNotNull('trans_date')
                    ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
                    ->sum('deposit_amount')
                : 0;
        }

        return [
            'months' => $months,
            'deposits' => $deposits,
            'office_receipts' => $officeReceipts,
            'invoices' => $invoices,
            'journal_receipts' => $journalReceipts,
        ];
    }

    /**
     * Get top clients by transaction volume
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $limit
     * @param int|null $receiptType Filter by receipt type (null = all)
     * @return array
     */
    public function getTopClients($startDate, $endDate, $limit = 5, $receiptType = null)
    {
        // Convert dates to dd/mm/yyyy format
        $startDateStr = $startDate->format('d/m/Y');
        $endDateStr = $endDate->format('d/m/Y');
        
        $query = DB::table('account_client_receipts as acr')
            ->join('admins', 'admins.id', '=', 'acr.client_id')
            ->select(
                'acr.client_id',
                'admins.first_name',
                'admins.last_name',
                'admins.client_id as client_unique_id',
                DB::raw('SUM(acr.deposit_amount) as total_deposits'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->whereNotNull('acr.trans_date')
            ->whereRaw("TO_DATE(acr.trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr]);
        
        // Apply receipt_type filter if provided
        if ($receiptType !== null) {
            $query->where('acr.receipt_type', $receiptType);
        }
        
        $topClients = $query
            ->groupBy('acr.client_id', 'admins.first_name', 'admins.last_name', 'admins.client_id')
            ->orderByDesc('total_deposits')
            ->limit($limit)
            ->get();

        return $topClients->map(function($client) {
            return [
                'client_id' => $client->client_id,
                'name' => trim($client->first_name . ' ' . $client->last_name),
                'client_unique_id' => $client->client_unique_id,
                'total_deposits' => $client->total_deposits,
                'transaction_count' => $client->transaction_count,
            ];
        })->toArray();
    }

    /**
     * Calculate trend percentage change
     * 
     * @param float $previous
     * @param float $current
     * @return array
     */
    private function calculateTrendPercentage($previous, $current)
    {
        if ($previous == 0) {
            return [
                'percentage' => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'neutral',
            ];
        }

        $change = (($current - $previous) / $previous) * 100;
        
        return [
            'percentage' => abs(round($change, 1)),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
        ];
    }

    /**
     * Get payment method breakdown
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    public function getPaymentMethodBreakdown($startDate, $endDate)
    {
        // Convert dates to dd/mm/yyyy format
        $startDateStr = $startDate->format('d/m/Y');
        $endDateStr = $endDate->format('d/m/Y');
        
        $breakdown = DB::table('account_client_receipts')
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(deposit_amount) as total'))
            ->whereNotNull('payment_method')
            ->whereNotNull('trans_date')
            ->whereRaw("TO_DATE(trans_date, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')", [$startDateStr, $endDateStr])
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        return $breakdown->map(function($item) {
            return [
                'method' => $item->payment_method ?: 'Not Specified',
                'count' => $item->count,
                'total' => $item->total,
            ];
        })->toArray();
    }
}

