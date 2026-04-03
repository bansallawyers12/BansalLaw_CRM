<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\AccountAllInvoiceReceipt;
use PDF;

class SendHubdocInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $invoiceId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Increase execution time limit for PDF generation
            set_time_limit(300); // 5 minutes
            
            // Get invoice data
            $record_get = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->get();
            
            if(empty($record_get) || count($record_get) == 0) {
                Log::error('Hubdoc Invoice Job: Invoice not found for ID: ' . $this->invoiceId);
                return;
            }

            // Get client info
            $clientname = DB::table('admins')->where('id',$record_get[0]->client_id)->first();
            
            // Calculate amounts
            $total_Invoice_Amount = AccountAllInvoiceReceipt::where('receipt_type', 3)
                ->where('receipt_id', $this->invoiceId)
                ->sum(DB::raw("CASE
                    WHEN payment_type = 'Discount' THEN -withdraw_amount
                    ELSE withdraw_amount
                END"));

            // Get all required data for PDF generation
            $record_get_Professional_Fee_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Professional Fee')->count();
            $record_get_Department_Charges_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Department Charges')->count();
            $record_get_Surcharge_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Surcharge')->count();
            $record_get_Disbursements_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Disbursements')->count();
            $record_get_Other_Cost_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Other Cost')->count();
            $record_get_Discount_cnt = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Discount')->count();

            $record_get_Professional_Fee = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Professional Fee')->get();
            $record_get_Department_Charges = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Department Charges')->get();
            $record_get_Surcharge = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Surcharge')->get();
            $record_get_Disbursements = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Disbursements')->get();
            $record_get_Other_Cost = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Other Cost')->get();
            $record_get_Discount = AccountAllInvoiceReceipt::where('receipt_type',3)->where('receipt_id',$this->invoiceId)->where('payment_type','Discount')->get();

            $total_Gross_Amount = AccountAllInvoiceReceipt::where('receipt_type', 3)
                ->where('receipt_id', $this->invoiceId)
                ->sum(DB::raw("
                    CASE
                        WHEN payment_type = 'Discount' AND gst_included = 'Yes' THEN -(withdraw_amount - (withdraw_amount / 11))
                        WHEN payment_type = 'Discount' AND gst_included = 'No' THEN -withdraw_amount
                        WHEN gst_included = 'Yes' THEN withdraw_amount - (withdraw_amount / 11)
                        ELSE withdraw_amount
                    END
                "));

            $total_GST_amount =  $total_Invoice_Amount - $total_Gross_Amount;

            $total_Pending_amount  = DB::table('account_client_receipts')
            ->where('receipt_type', 3)
            ->where('receipt_id', $this->invoiceId)
            ->where(function ($query) {
                $query->whereIn('invoice_status', [0, 2])
                    ->orWhere(function ($q) {
                        $q->where('invoice_status', 1)
                            ->where('balance_amount', '!=', 0);
                    });
            })
            ->sum('balance_amount');

            // Get payment method
            $invoice_payment_method = '';
            if( !empty($record_get) && $record_get[0]->invoice_no != '') {
                $office_receipt = DB::table('account_client_receipts')->select('payment_method')->where('receipt_type',2)->where('invoice_no',$record_get[0]->invoice_no)->first();
                if($office_receipt){
                    $invoice_payment_method = $office_receipt->payment_method;
                }
            }

            // Get client matter
            $client_matter_no = '';
            if( !empty($record_get) && $record_get[0]->client_matter_id != '') {
                $client_info = DB::table('admins')->select('client_id')->where('id',$record_get[0]->client_id)->first();
                if($client_info){
                    $client_unique_id = $client_info->client_id;
                } else {
                    $client_unique_id = '';
                }

                $matter_info = DB::table('client_matters')->select('client_unique_matter_no')->where('id',$record_get[0]->client_matter_id)->first();
                if($matter_info){
                    $client_unique_matter_no = $matter_info->client_unique_matter_no;
                    $client_matter_no = $client_unique_id.'-'.$client_unique_matter_no;
                } else {
                    $client_unique_matter_no = '';
                    $client_matter_no = '';
                }
            }

            // Generate PDF with optimized settings
            $pdf = PDF::setOptions([
                'isHtml5ParserEnabled' => true, 
                'isRemoteEnabled' => true,
                'logOutputFile' => storage_path('logs/log.htm'),
                'tempDir' => storage_path('logs/'),
                'chroot' => storage_path('logs/'),
                'enable_php' => false,
                'enable_javascript' => false,
                'enable_smart_shrinking' => true,
                'dpi' => 96,
                'default_font' => 'Arial'
            ])->loadView('emails.geninvoice',compact(
                ['record_get',
                'record_get_Professional_Fee_cnt',
                'record_get_Department_Charges_cnt',
                'record_get_Surcharge_cnt',
                'record_get_Disbursements_cnt',
                'record_get_Other_Cost_cnt',
                'record_get_Discount_cnt',

                'record_get_Professional_Fee',
                'record_get_Department_Charges',
                'record_get_Surcharge',
                'record_get_Disbursements',
                'record_get_Other_Cost',
                'record_get_Discount',

                'total_Gross_Amount',
                'total_Invoice_Amount',
                'total_GST_amount',
                'total_Pending_amount',

                'clientname',
                'invoice_payment_method',
                'client_matter_no'
            ]));

            // Save PDF to temporary file
            $pdfFileName = 'Invoice_' . $record_get[0]->invoice_no . '_' . time() . '.pdf';
            $pdfPath = storage_path('app/temp/' . $pdfFileName);
            
            // Create temp directory if it doesn't exist
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
            
            $pdf->save($pdfPath);

            // Prepare email data
            $invoiceData = [
                'invoice_no' => $record_get[0]->invoice_no ?? 'N/A',
                'client_name' => $clientname->name ?? 'N/A',
                'invoice_date' => $record_get[0]->trans_date ?? 'N/A',
                'amount' => $total_Invoice_Amount,
                'pdf_path' => $pdfPath,
                'file_name' => $pdfFileName
            ];

            // Send email to Hubdoc
            Mail::mailer('sendgrid')->to(env('HUBDOC_EMAIL'))->send(new \App\Mail\HubdocInvoiceMail($invoiceData));

            // Mark invoice as sent to Hubdoc
            $updateResult = DB::table('account_client_receipts')
                ->where('receipt_type', 3)
                ->where('receipt_id', $this->invoiceId)
                ->update([
                    'hubdoc_sent' => true,
                    'hubdoc_sent_at' => now()
                ]);

            // Log the update result
            Log::info('Hubdoc Invoice Job: Database update result for ID ' . $this->invoiceId . ': ' . $updateResult . ' rows affected');
            
            // If no rows were updated, log additional debugging info
            if ($updateResult == 0) {
                $record = DB::table('account_client_receipts')
                    ->where('receipt_type', 3)
                    ->where('receipt_id', $this->invoiceId)
                    ->first();
                
                Log::error('Hubdoc Invoice Job: No record found to update. Receipt ID: ' . $this->invoiceId);
                Log::error('Hubdoc Invoice Job: Available records with receipt_id ' . $this->invoiceId . ': ' . json_encode($record));
            }

            // Clean up temporary file
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }

            Log::info('Hubdoc Invoice Job: Invoice sent successfully for ID: ' . $this->invoiceId);

        } catch (\Exception $e) {
            // Clean up any temporary files in case of error
            if (isset($pdfPath) && file_exists($pdfPath)) {
                unlink($pdfPath);
            }
            
            Log::error('Hubdoc Invoice Job Error: ' . $e->getMessage() . ' for Invoice ID: ' . $this->invoiceId);
            throw $e;
        }
    }
}
