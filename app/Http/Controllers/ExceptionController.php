<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;

use Cookie;

class ExceptionController extends Controller
{
	public function __construct(Request $request)
    {
		//remove all cokies start
			\Cookie::queue(\Cookie::forget('product_id'));
			\Cookie::queue(\Cookie::forget('product_other_info_id'));
			\Cookie::queue(\Cookie::forget('quantity'));
			\Cookie::queue(\Cookie::forget('subject_ids'));
		//remove all cokies end	
	}
	
	public function index(Request $request)
	{
		if ($request->isMethod('post')) 
		{
			$this->validate($request, [
										'comment' => 'required'
									  ]);	
			$requestData 		= 	$request->all();
			
			echo "<pre>";
			print_r($requestData);die;	
		}
		
		return view('exception');
	}
}
