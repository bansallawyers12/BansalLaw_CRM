@extends('layouts.crm_client_detail')
@section('title', 'Financial Analytics Dashboard')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<link rel="stylesheet" href="{{ asset('css/financial-analytics-dashboard.css') }}">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endsection

@section('content')
<div class="analytics-container financial-analytics-dashboard">
    <!-- Page Header -->
    <div class="analytics-header">
        <h1><i class="fas fa-chart-line"></i> Financial Analytics Dashboard</h1>
        <p class="analytics-header-lead">Comprehensive overview of your financial performance and key metrics</p>
        <p class="analytics-header-period">
            <i class="fas fa-calendar-alt"></i> Data period: {{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}
        </p>
        
        <!-- Date Range Selector -->
        <div class="date-range-selector">
            <form method="GET" action="{{ route('clients.analytics-dashboard') }}" id="dateRangeForm">
                @if($receiptType !== null)
                <input type="hidden" name="receipt_type" value="{{ $receiptType }}">
                @endif
                
                <div class="date-range-field">
                    <label for="quick_select">Quick Select:</label>
                    <select name="quick_select" id="quick_select" onchange="handleQuickSelect(this.value)">
                        <option value="" {{ $quickSelect === '' ? 'selected' : '' }}>Custom Range</option>
                        <option value="this_month" {{ $quickSelect === 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ $quickSelect === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_quarter" {{ $quickSelect === 'this_quarter' ? 'selected' : '' }}>This Quarter</option>
                        <option value="this_year" {{ $quickSelect === 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="last_30_days" {{ $quickSelect === 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="last_90_days" {{ $quickSelect === 'last_90_days' ? 'selected' : '' }}>Last 90 Days</option>
                    </select>
                </div>
                
                <div class="date-range-field">
                    <label for="start_date">From:</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate->format('Y-m-d') }}">
                </div>
                
                <div class="date-range-field">
                    <label for="end_date">To:</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate->format('Y-m-d') }}">
                </div>
                
                <button type="submit" class="btn-apply">
                    <i class="fas fa-sync-alt"></i> Apply
                </button>
            </form>
        </div>
    </div>

    @php
        $metricValues = [
            $dashboardStats['monthly_stats']['total_deposits'] ?? 0,
            $dashboardStats['monthly_stats']['deposit_count'] ?? 0,
            $dashboardStats['monthly_stats']['total_fee_transfers'] ?? 0,
            $dashboardStats['monthly_stats']['total_office_receipts'] ?? 0,
            $dashboardStats['monthly_stats']['office_receipt_count'] ?? 0,
            $dashboardStats['monthly_stats']['total_invoices_issued'] ?? 0,
            $dashboardStats['monthly_stats']['invoice_count'] ?? 0,
            $dashboardStats['monthly_stats']['total_journal_receipts'] ?? 0,
            $dashboardStats['monthly_stats']['journal_receipt_count'] ?? 0,
            $dashboardStats['receipt_stats']['allocated_count'] ?? 0,
            $dashboardStats['receipt_stats']['unallocated_count'] ?? 0,
            $dashboardStats['invoice_stats']['unpaid_invoices'] ?? 0,
            $dashboardStats['invoice_stats']['unpaid_amount'] ?? 0,
            $dashboardStats['invoice_stats']['paid_invoices'] ?? 0,
            $dashboardStats['invoice_stats']['total_invoices'] ?? 0,
            $dashboardStats['allocation_metrics']['average_days_to_allocate'] ?? 0,
            $dashboardStats['allocation_metrics']['old_unallocated_count'] ?? 0,
        ];

        $trendSums = [
            array_sum($dashboardStats['trend_data']['deposits'] ?? []),
            array_sum($dashboardStats['trend_data']['office_receipts'] ?? []),
            array_sum($dashboardStats['trend_data']['invoices'] ?? []),
        ];

        $metricValues = array_merge($metricValues, $trendSums);

        $hasData = collect($metricValues)->contains(function ($value) {
            return is_numeric($value) && (float) $value > 0;
        });

        if (!$hasData && isset($dashboardStats['top_clients'])) {
            $hasData = collect($dashboardStats['top_clients'])->contains(function ($client) {
                return (isset($client['total_deposits']) && (float) $client['total_deposits'] > 0)
                    || (isset($client['transaction_count']) && (int) $client['transaction_count'] > 0);
            });
        }
    @endphp

    @unless($hasData)
    <div class="analytics-empty-alert" role="status">
        <i class="fas fa-info-circle" aria-hidden="true"></i>
        <div>
            <strong>No data available</strong>
            <p>
                There are no transactions for the selected date range ({{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}).
                Try selecting a different period or ensure recent transactions have been recorded.
            </p>
        </div>
    </div>
    @endunless

    <!-- Receipt Type Tabs -->
    <div class="analytics-tabs">
        <div class="tabs-nav" role="tablist" aria-label="Transaction type filter">
            <button class="tab-item {{ $receiptType === null ? 'active' : '' }}"
                    data-type=""
                    onclick="switchTab('')"
                    role="tab"
                    aria-label="View all transaction types"
                    aria-selected="{{ $receiptType === null ? 'true' : 'false' }}">
                <i class="fas fa-chart-pie" aria-hidden="true"></i> All Types
            </button>
            <button class="tab-item {{ $receiptType == 1 ? 'active' : '' }}"
                    data-type="1"
                    onclick="switchTab('1')"
                    role="tab"
                    aria-label="View client receipts only"
                    aria-selected="{{ $receiptType == 1 ? 'true' : 'false' }}">
                <i class="fas fa-receipt" aria-hidden="true"></i> Client Receipts
            </button>
            <button class="tab-item {{ $receiptType == 2 ? 'active' : '' }}"
                    data-type="2"
                    onclick="switchTab('2')"
                    role="tab"
                    aria-label="View office receipts only"
                    aria-selected="{{ $receiptType == 2 ? 'true' : 'false' }}">
                <i class="fas fa-building" aria-hidden="true"></i> Office Receipts
            </button>
            <button class="tab-item {{ $receiptType == 3 ? 'active' : '' }}"
                    data-type="3"
                    onclick="switchTab('3')"
                    role="tab"
                    aria-label="View invoices only"
                    aria-selected="{{ $receiptType == 3 ? 'true' : 'false' }}">
                <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Invoices
            </button>
            <button class="tab-item {{ $receiptType == 4 ? 'active' : '' }}"
                    data-type="4"
                    onclick="switchTab('4')"
                    role="tab"
                    aria-label="View journal receipts only"
                    aria-selected="{{ $receiptType == 4 ? 'true' : 'false' }}">
                <i class="fas fa-book" aria-hidden="true"></i> Journal Receipts
            </button>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div class="stats-grid">
        <!-- Total Deposits -->
        @if($receiptType === null || $receiptType == 1)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Total Deposits</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_deposits'], 2) }}</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['monthly_stats']['deposit_count'], 0) }} transactions</div>
                </div>
                <div class="stat-card-icon blue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            @if($dashboardStats['monthly_stats']['trends']['deposits']['direction'] != 'neutral')
            <span class="stat-card-trend {{ $dashboardStats['monthly_stats']['trends']['deposits']['direction'] }}">
                <i class="fas fa-arrow-{{ $dashboardStats['monthly_stats']['trends']['deposits']['direction'] == 'up' ? 'up' : 'down' }}"></i>
                {{ $dashboardStats['monthly_stats']['trends']['deposits']['percentage'] }}% vs last period
            </span>
            @endif
        </div>
        @endif

        <!-- Total Fee Transfers -->
        @if($receiptType === null || $receiptType == 1)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Fee Transfers</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_fee_transfers'], 2) }}</div>
                    <div class="stat-card-subtitle">This period</div>
                </div>
                <div class="stat-card-icon green">
                    <i class="fas fa-exchange-alt"></i>
                </div>
            </div>
        </div>
        @endif

        <!-- Total Office Receipts -->
        @if($receiptType === null || $receiptType == 2)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Office Receipts</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_office_receipts'], 2) }}</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['monthly_stats']['office_receipt_count'], 0) }} transactions</div>
                </div>
                <div class="stat-card-icon orange">
                    <i class="fas fa-building"></i>
                </div>
            </div>
            @if($dashboardStats['monthly_stats']['trends']['office_receipts']['direction'] != 'neutral')
            <span class="stat-card-trend {{ $dashboardStats['monthly_stats']['trends']['office_receipts']['direction'] }}">
                <i class="fas fa-arrow-{{ $dashboardStats['monthly_stats']['trends']['office_receipts']['direction'] == 'up' ? 'up' : 'down' }}"></i>
                {{ $dashboardStats['monthly_stats']['trends']['office_receipts']['percentage'] }}% vs last period
            </span>
            @endif
        </div>
        @endif

        <!-- Unallocated Receipts -->
        @if($receiptType === null || $receiptType == 1)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Unallocated Receipts</div>
                    <div class="stat-card-value">{{ number_format($dashboardStats['receipt_stats']['unallocated_count'], 0) }}</div>
                    <div class="stat-card-subtitle">Require attention</div>
                </div>
                <div class="stat-card-icon purple">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <span class="stat-card-trend neutral">
                {{ $dashboardStats['receipt_stats']['allocation_percentage'] }}% allocation rate
            </span>
        </div>
        @endif

        <!-- Average Days to Allocate -->
        @if($receiptType === null || $receiptType == 1)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Avg. Days to Allocate</div>
                    <div class="stat-card-value">{{ number_format($dashboardStats['allocation_metrics']['average_days_to_allocate'], 1) }}</div>
                    <div class="stat-card-subtitle">Processing time</div>
                </div>
                <div class="stat-card-icon teal">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            @if($dashboardStats['allocation_metrics']['old_unallocated_count'] > 0)
            <span class="stat-card-trend down">
                {{ number_format($dashboardStats['allocation_metrics']['old_unallocated_count'], 0) }} receipts > 30 days old
            </span>
            @endif
        </div>
        @endif

        <!-- Unpaid Invoices -->
        @if($receiptType === null || $receiptType == 3)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Unpaid Invoices</div>
                    <div class="stat-card-value">{{ number_format($dashboardStats['invoice_stats']['unpaid_invoices'], 0) }}</div>
                    <div class="stat-card-subtitle">${{ number_format($dashboardStats['invoice_stats']['unpaid_amount'], 2) }} outstanding</div>
                </div>
                <div class="stat-card-icon red">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </div>
            @if($dashboardStats['invoice_stats']['overdue_invoices'] > 0)
            <span class="stat-card-trend down">
                {{ number_format($dashboardStats['invoice_stats']['overdue_invoices'], 0) }} overdue
            </span>
            @endif
        </div>
        @endif

        <!-- Invoice Payment Rate -->
        @if($receiptType === null || $receiptType == 3)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Invoice Payment Rate</div>
                    <div class="stat-card-value">{{ $dashboardStats['invoice_stats']['payment_rate'] }}%</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['invoice_stats']['paid_invoices'], 0) }} of {{ number_format($dashboardStats['invoice_stats']['total_invoices'], 0) }} paid</div>
                </div>
                <div class="stat-card-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        @endif

        <!-- Total Invoices Issued -->
        @if($receiptType === null || $receiptType == 3)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Invoices Issued</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_invoices_issued'], 2) }}</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['monthly_stats']['invoice_count'], 0) }} invoices</div>
                </div>
                <div class="stat-card-icon blue">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
        </div>
        @endif

        <!-- Total Journal Receipts -->
        @if($receiptType === null || $receiptType == 4)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Journal Receipts</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_journal_receipts'] ?? 0, 2) }}</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['monthly_stats']['journal_receipt_count'] ?? 0, 0) }} transactions</div>
                </div>
                <div class="stat-card-icon teal">
                    <i class="fas fa-book"></i>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Charts Section -->
    <div class="chart-grid">
        <!-- Trend Chart -->
        <div class="chart-card full-width">
            <h3 class="chart-card-title">
                <i class="fas fa-chart-line"></i> 6-Month Financial Trend
            </h3>
            <div class="chart-container">
                <canvas id="trendChart" role="img" aria-label="Line chart showing 6-month financial trend for deposits, office receipts, and invoices"></canvas>
            </div>
        </div>

        <!-- Payment Method Breakdown -->
        <div class="chart-card">
            <h3 class="chart-card-title">
                <i class="fas fa-credit-card"></i> Payment Methods
            </h3>
            <div class="chart-container">
                <canvas id="paymentMethodChart" role="img" aria-label="Doughnut chart showing payment method distribution"></canvas>
            </div>
        </div>

        <!-- Receipt Allocation Status -->
        <div class="chart-card">
            <h3 class="chart-card-title">
                <i class="fas fa-tasks"></i> Receipt Allocation
            </h3>
            <div class="chart-container">
                <canvas id="allocationChart" role="img" aria-label="Pie chart showing allocated versus unallocated receipt ratio"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Clients Table -->
    @if(isset($dashboardStats['top_clients']) && count($dashboardStats['top_clients']) > 0)
    <div class="table-card">
        <h3 class="table-card-title">
            <i class="fas fa-trophy"></i> Top Clients by Transaction Volume
        </h3>
        <table class="analytics-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Client ID</th>
                    <th>Client Name</th>
                    <th>Total Deposits</th>
                    <th>Transactions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dashboardStats['top_clients'] as $index => $client)
                <tr>
                    <td>
                        @if($index == 0)
                            <i class="fas fa-trophy rank-icon--gold" aria-hidden="true"></i> #{{ $index + 1 }}
                        @elseif($index == 1)
                            <i class="fas fa-medal rank-icon--silver" aria-hidden="true"></i> #{{ $index + 1 }}
                        @elseif($index == 2)
                            <i class="fas fa-award rank-icon--bronze" aria-hidden="true"></i> #{{ $index + 1 }}
                        @else
                            #{{ $index + 1 }}
                        @endif
                    </td>
                    <td><strong>{{ $client['client_unique_id'] }}</strong></td>
                    <td class="client-name">{{ $client['name'] }}</td>
                    <td class="amount">${{ number_format($client['total_deposits'], 2) }}</td>
                    <td>{{ number_format($client['transaction_count'], 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="table-card">
        <h3 class="table-card-title">
            <i class="fas fa-trophy"></i> Top Clients by Transaction Volume
        </h3>
               <div class="table-empty-state">
            <i class="fas fa-users" aria-hidden="true"></i>
            <p>No client data available for this period</p>
        </div>
    </div>
    @endif

    <!-- Quick Links -->
    <h3 class="analytics-quick-access-title">
        <i class="fas fa-link" aria-hidden="true"></i> Quick Access
    </h3>
    <div class="quick-links">
        <a href="{{ route('clients.clientreceiptlist') }}" class="quick-link-card">
            <i class="fas fa-receipt"></i>
            <h4>Client Receipts</h4>
        </a>
        <a href="{{ route('clients.invoicelist') }}" class="quick-link-card">
            <i class="fas fa-file-invoice-dollar"></i>
            <h4>Invoice Lists</h4>
        </a>
        <a href="{{ route('clients.officereceiptlist') }}" class="quick-link-card">
            <i class="fas fa-building"></i>
            <h4>Office Receipts</h4>
        </a>
        <a href="{{ route('clients.journalreceiptlist') }}" class="quick-link-card">
            <i class="fas fa-book"></i>
            <h4>Journal Receipts</h4>
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js library not loaded. Charts will not display.');
        return;
    }

    const chartColors = {
        blue: 'rgba(30, 61, 96, 0.85)',
        green: 'rgba(30, 122, 82, 0.85)',
        orange: 'rgba(200, 153, 42, 0.85)',
        purple: 'rgba(58, 111, 168, 0.85)',
        red: 'rgba(168, 48, 32, 0.85)',
    };

    // Trend Chart
    try {
        const trendData = @json($dashboardStats['trend_data'] ?? null);

        if (!trendData || !trendData.months || trendData.months.length === 0) {
            document.getElementById('trendChart').parentElement.innerHTML =
                '<p class="chart-placeholder-message"><i class="fas fa-info-circle" aria-hidden="true"></i> No trend data available for the selected period</p>';
        } else {
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendData.months,
                    datasets: [
                        {
                            label: 'Deposits',
                            data: trendData.deposits || [],
                            borderColor: chartColors.blue,
                            backgroundColor: 'rgba(30, 61, 96, 0.12)',
                            tension: 0.4,
                            fill: true,
                        },
                        {
                            label: 'Office Receipts',
                            data: trendData.office_receipts || [],
                            borderColor: chartColors.green,
                            backgroundColor: 'rgba(30, 122, 82, 0.12)',
                            tension: 0.4,
                            fill: true,
                        },
                        {
                            label: 'Invoices',
                            data: trendData.invoices || [],
                            borderColor: chartColors.orange,
                            backgroundColor: 'rgba(200, 153, 42, 0.12)',
                            tension: 0.4,
                            fill: true,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.parsed.y.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error initializing trend chart:', error);
        document.getElementById('trendChart').parentElement.innerHTML =
            '<p class="chart-placeholder-message chart-placeholder-message--error"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Error loading chart</p>';
    }

    // Payment Method Chart
    try {
        const paymentMethods = @json($paymentMethods ?? []);

        if (!paymentMethods || paymentMethods.length === 0) {
            document.getElementById('paymentMethodChart').parentElement.innerHTML =
                '<p class="chart-placeholder-message"><i class="fas fa-info-circle" aria-hidden="true"></i> No payment method data for this period</p>';
        } else {
            const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
            new Chart(paymentMethodCtx, {
                type: 'doughnut',
                data: {
                    labels: paymentMethods.map(pm => pm.method || 'Not Specified'),
                    datasets: [{
                        data: paymentMethods.map(pm => parseFloat(pm.total) || 0),
                        backgroundColor: [
                            chartColors.blue,
                            chartColors.green,
                            chartColors.orange,
                            chartColors.purple,
                            chartColors.red,
                        ],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.parsed.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                }
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error initializing payment method chart:', error);
        document.getElementById('paymentMethodChart').parentElement.innerHTML =
            '<p class="chart-placeholder-message chart-placeholder-message--error"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Error loading chart</p>';
    }

    // Allocation Chart
    try {
        const receiptStats = @json($dashboardStats['receipt_stats'] ?? null);

        if (!receiptStats || ((parseInt(receiptStats.allocated_count) || 0) === 0 && (parseInt(receiptStats.unallocated_count) || 0) === 0)) {
            document.getElementById('allocationChart').parentElement.innerHTML =
                '<p class="chart-placeholder-message"><i class="fas fa-info-circle" aria-hidden="true"></i> No allocation data available</p>';
        } else {
            const allocationCtx = document.getElementById('allocationChart').getContext('2d');
            new Chart(allocationCtx, {
                type: 'pie',
                data: {
                    labels: ['Allocated', 'Unallocated'],
                    datasets: [{
                        data: [
                            parseInt(receiptStats.allocated_count) || 0,
                            parseInt(receiptStats.unallocated_count) || 0
                        ],
                        backgroundColor: [
                            chartColors.green,
                            chartColors.orange,
                        ],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error initializing allocation chart:', error);
        document.getElementById('allocationChart').parentElement.innerHTML =
            '<p class="chart-placeholder-message chart-placeholder-message--error"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Error loading chart</p>';
    }
});

function switchTab(receiptType) {
    // Show loading indicator
    const loader = document.querySelector('.loader');
    if (loader) {
        loader.style.display = 'block';
    }
    
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);

    if (receiptType === '' || receiptType === null) {
        params.delete('receipt_type');
    } else {
        params.set('receipt_type', receiptType);
    }

    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);

    window.location.href = url.pathname + '?' + params.toString();
}

function handleQuickSelect(value) {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const today = new Date();

    let startDate;
    let endDate;

    switch (value) {
        case 'this_month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'last_month':
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'this_quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            startDate = new Date(today.getFullYear(), quarter * 3, 1);
            endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
            break;
        case 'this_year':
            startDate = new Date(today.getFullYear(), 0, 1);
            endDate = new Date(today.getFullYear(), 11, 31);
            break;
        case 'last_30_days':
            startDate = new Date(today);
            startDate.setDate(startDate.getDate() - 30);
            endDate = today;
            break;
        case 'last_90_days':
            startDate = new Date(today);
            startDate.setDate(startDate.getDate() - 90);
            endDate = today;
            break;
        default:
            return;
    }

    if (startDate && endDate) {
        startDateInput.value = startDate.toISOString().split('T')[0];
        endDateInput.value = endDate.toISOString().split('T')[0];
        // Auto-submit the form when a quick select option is chosen
        document.getElementById('dateRangeForm').submit();
    }
}

const dateRangeForm = document.getElementById('dateRangeForm');
if (dateRangeForm) {
    dateRangeForm.addEventListener('submit', function() {
        const loader = document.querySelector('.loader');
        if (loader) {
            loader.style.display = 'block';
        }
    });
}

window.switchTab = switchTab;
window.handleQuickSelect = handleQuickSelect;

</script>
@endpush

