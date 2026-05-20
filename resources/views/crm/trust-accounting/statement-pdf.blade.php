<!doctype html>
<html>
<head>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #333; font-size: 13px; line-height: 1.5; }
        .wrap { max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #1b3a6b; font-size: 22px; margin: 0 0 8px; }
        .meta { margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #1b3a6b; color: #fff; padding: 8px 6px; text-align: left; font-size: 11px; }
        td { border-bottom: 1px solid #eee; padding: 7px 6px; font-size: 12px; }
        .text-end { text-align: right; }
        .summary { background: #eef1f7; padding: 12px; margin: 16px 0; border-left: 4px solid #1b3a6b; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Trust Account Statement</h1>
    <div class="meta">
        <strong>Bansal Lawyers</strong> — law practice trust account<br/>
        <strong>Client:</strong> {{ trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) }} ({{ $client_ref }})<br/>
        @if($address)
            {{ $address->address_line_1 ?? $address->address ?? '' }}
            @if(!empty($address->address_line_2)), {{ $address->address_line_2 }}@endif
            @if(!empty($address->suburb)), {{ $address->suburb }}@endif
            @if(!empty($address->state)) {{ $address->state }}@endif
            @if(!empty($address->zip)) {{ $address->zip }}@endif
            <br/>
        @endif
        <strong>Matter:</strong> {{ $matter_display }}<br/>
        @if($from_date && $to_date)
            <strong>Period:</strong> {{ \Carbon\Carbon::parse($from_date)->format('d/m/Y') }} to {{ \Carbon\Carbon::parse($to_date)->format('d/m/Y') }}<br/>
        @elseif($to_date)
            <strong>As at:</strong> {{ \Carbon\Carbon::parse($to_date)->format('d/m/Y') }}<br/>
        @else
            <strong>Statement:</strong> All transactions on record<br/>
        @endif
        <strong>Generated:</strong> {{ $generated_at }}
    </div>

    <div class="summary">
        Opening balance: <strong>${{ number_format($opening_balance, 2) }}</strong><br/>
        Closing balance: <strong>${{ number_format($closing_balance, 2) }}</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Ref</th>
                <th>Type</th>
                <th>Description</th>
                <th class="text-end">Deposit</th>
                <th class="text-end">Payment</th>
                <th class="text-end">Balance</th>
            </tr>
        </thead>
        <tbody>
        @forelse($entries as $row)
            <tr>
                <td>{{ $row->trans_date }}</td>
                <td>{{ $row->trans_no }}</td>
                <td>{{ $row->client_fund_ledger_type }}</td>
                <td>{{ $row->description }}@if($row->payment_method)<br/><small>Method: {{ $row->payment_method }}</small>@endif</td>
                <td class="text-end">@if((float)$row->deposit_amount > 0)${{ number_format((float)$row->deposit_amount, 2) }}@else—@endif</td>
                <td class="text-end">@if((float)$row->withdraw_amount > 0)${{ number_format((float)$row->withdraw_amount, 2) }}@else—@endif</td>
                <td class="text-end">${{ number_format((float)($row->balance_amount ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7">No trust transactions in this period.</td></tr>
        @endforelse
        </tbody>
    </table>

    <p style="margin-top: 24px; font-size: 12px; color: #555;">
        This statement shows all trust money held on your behalf for the matter above, in accordance with Rule 52 of the Legal Profession Uniform General Rules 2015.
    </p>
</div>
</body>
</html>
