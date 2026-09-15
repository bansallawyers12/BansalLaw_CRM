@php
    $includeTransNo = $includeTransNo ?? false;
@endphp
<tr>
    <th title="Work date or date range shown on the tax invoice">Date</th>
    <th title="Date this entry was posted in the system">Date recorded</th>
    @if($includeTransNo)
        <th>Trans. No</th>
    @endif
    <th title="Type of charge being invoiced">Charge type</th>
    <th>Description</th>
    <th title="Staff member who did the work">Fee earner</th>
    <th title="Role billed on this line (e.g. Solicitor, Paralegal)">Role</th>
    <th title="Hourly or fixed fee">Basis</th>
    <th>Hrs</th>
    <th>Rate (ex GST)</th>
    <th>Amount (ex GST)</th>
    <th>GST</th>
    <th></th>
</tr>
