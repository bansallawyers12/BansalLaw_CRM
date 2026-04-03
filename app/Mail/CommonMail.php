<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;

use Illuminate\Mail\Mailable;

use Illuminate\Queue\SerializesModels;

use Illuminate\Contracts\Queue\ShouldQueue;

class CommonMail extends Mailable
{
	use Queueable, SerializesModels;
	
	public $content;
	public $subject;
	public $sender;
	public $senderfrom;
	public $array;
	/**
	* Create a new message instance.
	*
	* @return void
	*/
	public function __construct($content, $subject, $sender, $senderfrom, $array) {
		$this->content = $content;
		$this->subject = $subject;
		$this->sender = $sender;
		$this->senderfrom = $senderfrom;
		 $this->array = $array;
	}
	/**
	* Build the message.
	*
	* @return $this
	*/
	public function build() {	
		  $email = $this->from($this->sender)->subject($this->subject)->view('emails.common');
		 if(isset($this->array['file'])){
			$email->attach($this->array['file'],[
				 'as' => $this->array['file_name'],
				 'mime' => 'application/pdf'
			 ]);
			 }
		    if(isset($this->array['files']) && !empty($this->array['files'])){
			    foreach($this->array['files'] as $l){
			     $email->attach($l);
			    }
			  }	 
			 if(isset($this->array['filesatta']) && !empty($this->array['filesatta'])){
			    foreach($this->array['filesatta'] as $file){
			        
			    $email->attach($file->getRealPath(), [
        'as' => $file->getClientOriginalName(),
        'mime' => $file->getMimeType(),
    ]);
			    }
			  }	
			return $email;
	}
}
