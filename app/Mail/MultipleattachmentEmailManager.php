<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MultipleattachmentEmailManager extends Mailable
{
    use Queueable, SerializesModels;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $array;

    public function __construct($array)
    {
        $this->array = $array;
    }
    /**
     * Build the message.
     *
     * @return $this
     */
     public function build()
     {
          $email = $this->view($this->array['view'])
                     ->from($this->array['from'], $this->array['name'])
                     ->subject($this->array['subject']);
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
					
			return $email;
					
     }
 }
