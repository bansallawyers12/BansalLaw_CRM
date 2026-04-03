<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View All Constants
    |--------------------------------------------------------------------------
    |
	| All Globally constants declare...
    |
    */
	'unlimited' => '1000000',
	'limit' => '20',
	'empty' => 'N/A',
	'developer' =>'raghavgarg.jpiet@gmail.com',
	'no_data' =>'There are no data in this table.',
	'logoImg' => '/public/img/logo.png', 
	'test_pdfs' => public_path().'/img/test_pdfs',
	'test_suggestion_pdfs' => public_path().'/img/test_suggestion_pdfs',
	'test_submitted_copies' => public_path().'/img/test_submitted_copies',
	'test_reviewed_copies' => public_path().'/img/test_reviewed_copies',
	// profile_imgs removed – avatars use static img/avatar.png
	'invoice' => public_path().'/img/invoice', 
	// removed legacy tour-package paths: destination_img, topinclusion_img
	'pdfs' => public_path().'/img/pdfs',
	// removed legacy tour-package paths: packageimage, gallery_img, hotel_img, plans
	'product_img' => public_path().'/img/product_img',
	'service_imgs' => public_path().'/img/service_img',
	'product_sample_img' => public_path().'/img/product_sample_img',
	'themes_img' => public_path().'/img/themes_img',
	'attach_files' => public_path().'/img/attach_files',
	'attacment_file' => public_path().'/img/attacment_file',
	'checklists' => public_path().'/checklists',
	'blog' => public_path().'/img/blog',
	'free_imgs' => public_path().'/img/free_downloads/free_imgs',
	'free_audio' => public_path().'/img/free_downloads/audio',
	'free_video' => public_path().'/img/free_downloads/video',
	'free_pdf' => public_path().'/img/free_downloads/pdf',
	
	'logo' => public_path().'/img/logo',
	'documents' => public_path().'/img/documents',
	
	//error response
	'server_error' =>'There are some error into server end, so please try again later.',	
	'unauthorized' =>'You are not authorized person to access this page directly.',	
	'exception' =>'There are some exception issue into your request, so please write the comment below, what you did face.',	
	'post_method' =>'Method should be POST.',	
	'decode_string' =>'Encode String can not be converted, We think, you did pass the value manually, so please do not this.',
	'not_exist' => ' does not exist in our system, so please check it once again.',	
	'added' => ' has been added successfully.',	
	'edited' => ' has been edited successfully.',	

	// Reception user ID for office visit "please send the client" notifications (env RECEPTION_USER_ID)
	'reception_user_id' => env('RECEPTION_USER_ID', 36608),
];
