<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Models\Admin;
// use App\Models\EmailTemplate; // REMOVED: email_templates table has been deleted
// use App\Models\InvoiceFollowup; // REMOVED: InvoiceFollowup model has been deleted
// use App\Models\ShareInvoice; // REMOVED: ShareInvoice model has been deleted
 use PDF;
 use DateTime;
 use App\Mail\CommonMail;
use App\Mail\InvoiceEmailManager;
use Mail;

class CronJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CronJob:cronjob';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'User Name Change Successfully';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		// Invoice-related functionality disabled - invoice_payments, invoice_details, and invoices tables have been deleted
		// This cron job previously handled invoice reminder emails for invoices due in 2 days or on due date
		// All invoice model references (Invoice, InvoicePayment, InvoiceDetail) have been removed
		/* \DB::table('users')
            ->where('id', 1)
            ->update(['course_level' => str_random(10)]);
         Mail::send('emails.test', [], function($message)
        {
            $message->to('pankaj95.mca10.lgc@gmail.com', 'John Doe')->subject('Test');
        }); */
 
        $this->info('Test has fired.');
    }
	
	public static function send_attachment_email_template($invoicearray, $replace = array(), $replace_with = array(), $alias = null, $to = null, $subject = null, $sender = null) 
	{
		// email_templates table has been deleted - using fallback content
		$email_template	= 	DB::table('email_templates')->where('alias', $alias)->first();
		if(!$email_template) {
			\Log::warning('Email template not found for alias: ' . $alias . ' - email_templates table has been deleted');
			// Use a simple fallback email content
			$emailContent = 'Email template content is no longer available. Please contact support.';
		} else {
			$emailContent 	= 	$email_template->description;
			$emailContent	=	str_replace($replace,$replace_with,$emailContent);
		}
		if($subject == NULL)
		{
			$subject		=	$subject;	
		}	
		try {
			$explodeTo = explode(';', $to);//for multiple and single to
			$invoicearray['subject'] = $subject;
			$invoicearray['from'] = $sender;
			$invoicearray['content'] = $emailContent;
			Mail::mailer('sendgrid')->to($explodeTo)->queue(new InvoiceEmailManager($invoicearray));
			
			return true;
		} catch (\Exception $e) {
			\Log::error('Email sending failed in CronJob: ' . $e->getMessage());
			return false;
		}
		
	}
}
?>