<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class HubdocInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoiceData;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($invoiceData)
    {
        $this->invoiceData = $invoiceData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.hubdoc_invoice')
                    ->subject('Invoice for Hubdoc Processing')
                    ->attach($this->invoiceData['pdf_path'], [
                        'as' => $this->invoiceData['file_name'],
                        'mime' => 'application/pdf'
                    ]);
    }
}
