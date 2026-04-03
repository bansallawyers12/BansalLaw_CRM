<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EOI Confirmation</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #667eea;
            margin: 0;
            font-size: 28px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
        }
        .eoi-info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .eoi-info-table thead tr {
            background-color: #667eea;
            color: #ffffff;
        }
        .eoi-info-table th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid #5a67d8;
        }
        .eoi-info-table tbody td {
            padding: 12px 10px;
            border: 1px solid #e0e0e0;
            color: #333;
        }
        .eoi-info-table tbody tr {
            background-color: #f8f9fa;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 14px 30px;
            margin: 10px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .btn-confirm {
            background-color: #667eea;
            color: #ffffff;
            border: 2px solid #5a67d8;
        }
        .btn-confirm:hover {
            background-color: #5a67d8;
        }
        .btn-amend {
            background-color: #f8f9fa;
            color: #667eea;
            border: 2px solid #667eea;
        }
        .btn-amend:hover {
            background-color: #667eea;
            color: #ffffff;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
            font-size: 14px;
            color: #888;
            text-align: center;
        }
        .important-notice {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .important-notice p {
            margin: 0;
            color: #856404;
        }
        .points-total-box {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: #ffffff;
            padding: 24px;
            border-radius: 10px;
            text-align: center;
            margin: 25px 0 20px 0;
            max-width: 200px;
            margin-left: auto;
            margin-right: auto;
        }
        .points-total-number {
            font-size: 42px;
            font-weight: bold;
            margin: 0;
            line-height: 1.2;
        }
        .points-total-label {
            font-size: 14px;
            opacity: 0.95;
            margin: 8px 0 0 0;
        }
        .points-info-text {
            font-size: 12px;
            color: #6c757d;
            text-align: center;
            margin-top: 10px;
            padding: 0 10px;
        }
        .warning-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 12px 15px;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .warning-box p {
            margin: 0;
            color: #721c24;
            display: flex;
            align-items: start;
            line-height: 1.5;
        }
        .warning-icon {
            font-size: 18px;
            margin-right: 8px;
            flex-shrink: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        thead tr {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }
        th.points-col {
            width: 80px;
            text-align: center;
        }
        tbody tr {
            border-bottom: 1px solid #e9ecef;
        }
        td {
            padding: 10px;
            color: #495057;
        }
        td.category {
            font-weight: 500;
        }
        td.details {
            color: #6c757d;
        }
        td.points {
            text-align: center;
            font-weight: 600;
        }
        td.points.positive {
            color: #28a745;
        }
        td.points.zero {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>EOI Details Confirmation</h1>
        </div>

        @php
            $formatDetail = function ($detail, $fallback = 'N/A') {
                if ($detail === null || $detail === '') {
                    return $fallback;
                }
                if (is_array($detail)) {
                    $keys = array_keys($detail);
                    $isAssoc = $keys !== range(0, count($keys) - 1);
                    if ($isAssoc) {
                        return json_encode($detail);
                    }
                    $filtered = array_filter($detail, function ($value) {
                        return $value !== null && $value !== '';
                    });
                    return implode(', ', $filtered);
                }
                return $detail;
            };
        @endphp

        <p>Dear {{ $client->first_name }} {{ $client->last_name }},</p>

        <p>Please review and confirm the following Expression of Interest (EOI) details we have on record for you:</p>

        <div class="section">
            <h2>EOI Information</h2>
            <table class="eoi-info-table">
                <thead>
                    <tr>
                        <th>EOI Number</th>
                        <th>Subclass(es)</th>
                        <th>State(s)</th>
                        <th>Occupation</th>
                        <th>Points</th>
                        <th>Submission Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $eoiReference->EOI_number ?? 'N/A' }}</td>
                        <td>{{ $eoiReference->formatted_subclasses }}</td>
                        <td>{{ $eoiReference->formatted_states }}</td>
                        <td>{{ $eoiReference->EOI_occupation ?? 'N/A' }}</td>
                        <td><strong>{{ $eoiReference->EOI_point ?? 'N/A' }}</strong></td>
                        <td>
                            @if($eoiReference->EOI_submission_date)
                                {{ $eoiReference->EOI_submission_date->format('d/m/Y') }}
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if(!empty($attachmentLabels))
        <div class="section">
            <p><strong>Attachments for your reference:</strong> Please find the following document(s) attached to this email: {{ implode(', ', $attachmentLabels) }}.</p>
        </div>
        @endif

        {{-- Warnings Section --}}
        @if(!empty($pointsData['warnings']))
        <div class="section">
            <h2 style="color: #dc3545;">Upcoming Changes</h2>
            @foreach($pointsData['warnings'] as $warning)
            <div class="warning-box">
                <p>
                    <span class="warning-icon">&#9888;</span>
                    <span>{{ is_array($warning) ? ($warning['message'] ?? '') : $warning }}</span>
                </p>
            </div>
            @endforeach
        </div>
        @endif

        <div class="section">
            <p>Please review all the details carefully and let us know if any adjustments are required.</p>
            <p><strong>Kindly note the following points related to EOI:</strong></p>
            <ul>
                <li>In most instances, work experience points can only be claimed after the completion of your relevant education.</li>
                <li>To claim experience points, you must be working in your nominated or closely related occupation for at least 20 hours per week for at least 1 year.</li>
                <li>You can only claim points for work completed on a Substantive visa or BVA or BVB, which allows you to work. Work completed on BVC or BVE cannot be used to claim experience points.</li>
                <li>If your nominated occupation is a trade occupation, you are not eligible to receive points for the Professional Year.</li>
                <li>Points for regional studies can be claimed only if you have both studied and lived in the designated regional area throughout your course with no distance education.</li>
                <li>Experience gained during any period where you did not hold work rights, or if you worked in excess of your permitted work rights, cannot be counted towards your experience points.</li>
                <li>We have prepared the EOI based on the information provided and may not verify all your claims or points.</li>
            </ul>
            <p><strong>Additional requirement for State Nomination:</strong></p>
            <p>We require that your English test and Skills Assessment each have at least 12 weeks validity remaining at all times.</p>
            <p>Please ensure that you have provided us with all Points claimed work experience details strictly in accordance with the above parameters.</p>
        </div>

        <div class="important-notice">
            <p><strong>Important:</strong> Please carefully review all the information above. If everything is correct, click the "Confirm Details" button. If you need to make any changes, click "Request Amendment" and provide details.</p>
        </div>

        <div class="button-container">
            <a href="{{ $confirmUrl }}" class="btn btn-confirm">Confirm Details</a>
            <a href="{{ $amendUrl }}" class="btn btn-amend">Request Amendment</a>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply directly to this message.</p>
            <p>If you have any questions, please contact your migration agent.</p>
            <p style="margin-top: 15px; font-size: 14px;">
                Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf" style="color: #667eea; text-decoration: none; font-weight: 600;">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>
            </p>
        </div>
    </div>
</body>
</html>
