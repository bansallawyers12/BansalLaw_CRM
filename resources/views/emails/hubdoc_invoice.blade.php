<!DOCTYPE html>
<html>
<head>
    <title>Invoice for Hubdoc Processing</title>
</head>
<body>
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2>Invoice for Hubdoc Processing</h2>
        
        <p>Dear Hubdoc Team,</p>
        
        <p>Please find attached the invoice for processing.</p>
        
        <p><strong>Invoice Details:</strong></p>
        <ul>
            <li><strong>Invoice Number:</strong> {{ $invoiceData['invoice_no'] ?? 'N/A' }}</li>
            <li><strong>Client:</strong> {{ $invoiceData['client_name'] ?? 'N/A' }}</li>
            <li><strong>Date:</strong> {{ $invoiceData['invoice_date'] ?? 'N/A' }}</li>
            <li><strong>Amount:</strong> ${{ number_format($invoiceData['amount'] ?? 0, 2) }}</li>
        </ul>
        
        <p>Please process this invoice accordingly.</p>
        
        <p>Best regards,<br>
        Bansal Immigration Team</p>
    </div>
</body>
</html>
