<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Redirect;

use App\Models\Lead;
use App\Models\Admin;
use App\Models\Country;
// use App\Models\WebsiteSetting; // removed website settings dependency
// use App\Models\State; // REMOVED: State model has been deleted
use PDF;
use Auth;
use App\Models\ActivitiesLog;
use App\Models\Note;
use App\Models\ClientMatter;
use Carbon\Carbon;
use App\Models\ClientVisaCountry;
use App\Services\EmailService;
use App\Services\CrmSentEmailS3Service;
use App\Support\WorkflowStageFreeze;

class CRMUtilityController extends Controller
{
    use EnsuresCrmRecordAccess;

    protected $emailService;
    protected $crmSentEmailS3Service;

    public function __construct(EmailService $emailService, CrmSentEmailS3Service $crmSentEmailS3Service)
    {
        $this->middleware('auth:admin');
        $this->emailService = $emailService;
        $this->crmSentEmailS3Service = $crmSentEmailS3Service;
    }
    // Dashboard functionality moved to DashboardController

    public function fetchnotification(Request $request){
        // $notificalists = \App\Models\Notification::where('receiver_id', Auth::user()->id)->where('receiver_status', 0)->orderby('created_at','DESC')->paginate(5);
         $notificalistscount = \App\Models\Notification::where('receiver_id', Auth::user()->id)->where('receiver_status', 0)->count();
        /* $output = '';
	    foreach($notificalists as $listnoti){
	        $output .= '<a href="'.$listnoti->url.'?t='.$listnoti->id.'" class="dropdown-item dropdown-item-unread">
						<span class="dropdown-item-icon bg-primary text-white">
							<i class="fas fa-code"></i>
						</span>
						<span class="dropdown-item-desc">'.$listnoti->message.' <span class="time">'.date('d/m/Y h:i A',strtotime($listnoti->created_at)).'</span></span>
					</a>';
	    }*/

	    $data = array(
           //'notification' => $output,
           'unseen_notification'  => $notificalistscount
        );
        echo json_encode($data);
    }

    public function fetchmessages(Request $request){
        $notificalists = \App\Models\Notification::where('receiver_id', Auth::user()->id)->where('seen', 0)->first();
        if($notificalists){
            $obj = \App\Models\Notification::find($notificalists->id);
            $obj->seen = 1;
            $obj->save();
            return $notificalists->message;
        }else{
            return 0;
        }
    }

    // Moved to DashboardController

    // Moved to DashboardController

    // Dashboard notification methods moved to DashboardController

    /**
     * My Profile.
     *
     * @return \Illuminate\Http\Response
     */
	public function returnsetting(Request $request){
		return view('crm.settings.returnsetting');
	}
	public function myProfile(Request $request)
	{
		/* Get all Select Data */
			$countries = array();
		/* Get all Select Data */

		if ($request->isMethod('post'))
		{
			$requestData 		= 	$request->all();

			$this->validate($request, [
										'first_name' => 'required',
										'last_name' => 'nullable',
										'country' => 'required',
										'phone' => 'required',
										'state' => 'required',
										'city' => 'required',
										'address' => 'required',
										'zip' => 'required'
									  ]);

			$obj							= 	\App\Models\Staff::find(Auth::user()->id);

			$obj->first_name				=	@$requestData['first_name'];
			$obj->last_name					=	@$requestData['last_name'];
			$obj->phone						=	@$requestData['phone'];
			$obj->address					=	@$requestData['address'];
			$obj->company_name				=	@$requestData['company_name'];
			$obj->company_website			=	@$requestData['company_website'];

			$saved							=	$obj->save();

			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return Redirect::to('/my_profile')->with('success', 'Your Profile has been edited successfully.');
			}
		}
		else
		{
			$id = Auth::user()->id;
			$fetchedData = \App\Models\Staff::find($id);

			return view('crm.my_profile', compact(['fetchedData', 'countries']));
		}
	}
	/**
     * Change password and Logout automatiaclly.
     *
     * @return \Illuminate\Http\Response
     */
	public function change_password(Request $request)
	{
		//check authorization start
			/* $check = $this->checkAuthorizationAction('Admin', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			} */
		//check authorization end

		if ($request->isMethod('post'))
		{
			$this->validate($request, [
										'old_password' => 'required|min:6',
										'password' => 'required|confirmed|min:6',
										'password_confirmation' => 'required|min:6'
									  ]);

			$requestData 	= 	$request->all();
			$admin_id = Auth::user()->id;

			$fetchedData = \App\Models\Staff::where('id', '=', $admin_id)->first();
			if(!empty($fetchedData))
				{
					if($admin_id == trim($requestData['admin_id']))
						{
							 if (!(Hash::check($request->get('old_password'), Auth::user()->password)))
								{
									return redirect()->back()->with("error","Your current password does not matches with the password you provided. Please try again.");
								}
							else
								{
									$admin = \App\Models\Staff::find($requestData['admin_id']);
									$admin->password = Hash::make($requestData['password']);
									if($admin->save())
										{
											Auth::guard('admin')->logout();
											$request->session()->flush();

											return redirect()->route('dashboard')->with('success', 'Your Password has been changed successfully.');
										}
									else
										{
											return redirect()->back()->with('error', config('constants.server_error'));
										}
								}
						}
					else
						{
							return redirect()->back()->with('error', 'You can change the password only your account.');
						}
				}
			else
				{
					return redirect()->back()->with('error', 'Staff member does not exist, so you cannot change the password.');
				}
		}
		return view('crm.change_password');
	}


	public function websiteSetting(Request $request)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('Admin', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end

		if ($request->isMethod('post'))
		{
			$requestData 		= 	$request->all();

			$this->validate($request, [
										'phone' => 'required|max:20',
										'ofc_timing' => 'nullable|max:255',
										'email' => 'required|max:255'
									  ]);

			/* Logo Upload Function Start */
				if($request->hasfile('logo'))
				{
					/* Unlink File Function Start */
						if(@$requestData['logo'] != '')
							{
								$this->unlinkFile(@$requestData['old_logo'], config('constants.logo'));
							}
					/* Unlink File Function End */

					$logo = $this->uploadFile($request->file('logo'), config('constants.logo'));
				}
				else
				{
					$logo = @$requestData['old_logo'];
				}
			/* Logoe Upload Function End */

		// Website settings functionality disabled - WebsiteSetting model has been removed
		return redirect()->back()->with('error', 'Website settings functionality has been disabled - WebsiteSetting model has been removed');
		}
	else
	{
		// Website settings functionality disabled - WebsiteSetting model has been removed
		return redirect()->back()->with('error', 'Website settings functionality has been disabled - WebsiteSetting model has been removed');
	}
	}

	public function editapi(Request $request)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('api_key', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end
		if ($request->isMethod('post'))
		{
			$obj	= 	\App\Models\Staff::find(Auth::user()->id);
			$obj->client_id	=	md5(Auth::user()->id.time());
			$saved				=	$obj->save();
			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return Redirect::to('/api-key')->with('success', 'Api Key'.config('constants.edited'));
			}
		}else{
			return view('crm.apikey');
		}
	}

	public function updateAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);
			$requestData['current_status'] = trim($requestData['current_status']);
			$requestData['table'] = trim($requestData['table']);
			$requestData['col'] = trim($requestData['colname']);

			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id']) && isset($requestData['current_status']) && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{
							if($requestData['current_status'] == 0)
							{
								$updated_status = 1;
								$message = 'Record has been enabled successfully.';
							}
							else
							{
								$updated_status = 0;
								$message = 'Record has been disabled successfully.';
							}
							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update([$requestData['col'] => $updated_status]);
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error');
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		 die;

	}

	public function moveAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);
			$requestData['col'] = trim($requestData['col']);

				if(isset($requestData['id']) && !empty($requestData['id']) && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update([$requestData['col'] => 0]);
							if($response)
							{
								$status = 1;
								$message = 'Record successfully moved';
							}
							else
							{
								$message = config('constants.server_error');
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}

		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function declinedAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);

			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id'])  && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

								$updated_status = 2;
								$message = 'Record has been disabled successfully.';

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['status' => $updated_status]);
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error');
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function approveAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);

			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id'])  && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

								$updated_status = 1;
								$message = 'Record has been approved successfully.';

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['status' => $updated_status]);
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error').'sss';
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function processAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);

			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id'])  && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

								$updated_status = 4;
								$message = 'Record has been processed successfully.';

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['status' => $updated_status]);
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error').'sss';
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function archiveAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);

			$astatus = '';
			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id'])  && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

								$updated_status = 1;
								$message = 'Record has been archived successfully.';

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['is_archive' => $updated_status]);
							$getarchive 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->first();
							if($getarchive->status == 0){
								$astatus = '<span title="draft" class="ui label uppercase">Draft</span><span> (Archived)</span>';
							}else if($getarchive->status == 1){
								$astatus = '<span title="draft" class="ui label uppercase yellow">Sent</span><span> (Archived)</span>';
							}else if($getarchive->status == 2){
								$astatus = '<span title="draft" class="ui label uppercase text-danger">Declined</span><span> (Archived)</span>';
							}
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error');
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message, 'astatus'=>$astatus));
		die;
	}

	public function deleteAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all(); //dd($requestData);
            $requestData['id'] = trim($requestData['id']);
			$requestData['table'] = trim($requestData['table']);
            $role = Auth::user()->role;
            if(isset($requestData['id']) && !empty($requestData['id']) && isset($requestData['table']) && !empty($requestData['table']))
			{
				$tableExist = Schema::hasTable(trim($requestData['table']));
                if($tableExist)
				{
					$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();
                    if($recordExist)
					{
						if($requestData['table'] == 'admins'){
                            $o = \App\Models\Admin::where('id', $requestData['id'])->first();
							if($o->status == 1){
								$is_status = 0;
							}else{
								$is_status = 1;
							}
							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['status' => $is_status, 'updated_at' => date('Y-m-d H:i:s')]);
							if($response) {
								$status = 1;
                                if($is_status == 0 ) {
                                    $message = 'Record has been inactive successfully.';
                                } else {
                                    $message = 'Record has been active successfully.';
                                }
                            }
							else {
								$message = config('constants.server_error');
							}
						}
                        else if($requestData['table'] == 'client_matters'){
                            $response = DB::table($requestData['table'])->where('id', $requestData['id'])->update(['matter_status' => 0]);
							if($response) {
								$status = 1;
								$message = 'Record has been enabled successfully.';
							} else {
								$message = config('constants.server_error');
							}
						}
                        else if($requestData['table'] == 'quotations'){
                            $response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['is_archive' => 1]);
							if($response) {
								$status = 1;
								$message = 'Record has been enabled successfully.';
							} else {
								$message = config('constants.server_error');
							}
						}
                        else if($requestData['table'] == 'templates'){
                            $isexist	=	$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();
                            if($isexist){
                                $response	=	DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                                DB::table('template_infos')->where('quotation_id', @$requestData['id'])->delete();
                                if($response) {
                                    $status = 1;
                                    $message = 'Record has been deleted successfully.';
                                } else {
                                    $message = config('constants.server_error');
                                }
                            }else{
                                $message = 'ID does not exist, please check it once again.';
                            }
						}
                        else if($requestData['table'] == 'products'){
                            // applications table removed - product_id check no longer needed
                            $applicationisexist = false;

                            if($applicationisexist){
                                $message = "Can't Delete its have relation with other records";
                            }else{
                                $isexist	=	$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();
                                if($isexist){
                                $response	=	DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                                DB::table('template_infos')->where('quotation_id', @$requestData['id'])->delete();

                                if($response) {
                                    $status = 1;
                                    $message = 'Record has been deleted successfully.';
                                } else {
                                    $message = config('constants.server_error');
                                }
                                }else{
                                    $message = 'ID does not exist, please check it once again.';
                                }
                            }
                        }
                        else if($requestData['table'] == 'email_labels'){
                            $label = DB::table($requestData['table'])->where('id', $requestData['id'])->first();
                            if($label && $label->type == 'system'){
                                $message = 'System labels cannot be deleted.';
                            } else {
                                $isexist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();
                                if($isexist){
                                    $response = DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                                    if($response) {
                                        $status = 1;
                                        $message = 'Record has been deleted successfully.';
                                    } else {
                                        $message = config('constants.server_error');
                                    }
                                }else{
                                    $message = 'ID does not exist, please check it once again.';
                                }
                            }
                        }
                        else if ($requestData['table'] == 'workflow_stages') {
                            $row = DB::table('workflow_stages')->where('id', $requestData['id'])->first();
                            if ($row && WorkflowStageFreeze::isFrozen($row->name)) {
                                $message = 'This workflow stage is frozen and cannot be deleted.';
                            } else {
                                $response = DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                                if ($response) {
                                    $status = 1;
                                    $message = 'Record has been deleted successfully.';
                                } else {
                                    $message = config('constants.server_error');
                                }
                            }
                        }
                        else{
                            $response	=	DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                            if($response) {
                                $status = 1;
                                $message = 'Record has been deleted successfully.';
                            } else {
                                $message = config('constants.server_error');
                            }
                        }
					}
                    else
                    {
                        $message = 'ID does not exist, please check it once again.';
                    }
                }
                else
                {
                    $message = 'Table does not exist, please check it once again.';
                }
            }
            else
            {
                $message = 'Id OR Table does not exist, please check it once again.';
            }
        }
		else {
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function getStates(Request $request)
	{
		$status 			= 	0;
		$data				=	array();
		$method 			= 	$request->method();

		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			if(isset($requestData['id']) && !empty($requestData['id']))
			{
				$recordExist = Country::where('id', $requestData['id'])->exists();

			if($recordExist)
			{
				// State functionality disabled - State model has been removed
				$data = [];
				$status = 0;
				$message = 'State functionality has been disabled - State model has been removed';
			}
			else
			{
				$message = 'ID does not exist, please check it once again.';
			}
		}
		else
		{
			$message = 'ID does not exist, please check it once again.';
		}
	}
	else
	{
		$message = config('constants.post_method');
	}
	echo json_encode(array('status'=>$status, 'message'=>$message, 'data'=>$data));
	die;
}

public function getChapters(Request $request)
	{
		$status 			= 	0;
		$data				=	array();
		$method 			= 	$request->method();

		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			if(isset($requestData['id']) && !empty($requestData['id']))
			{
				$recordExist = McqSubject::where('id', $requestData['id'])->exists();

				if($recordExist)
				{
					$data 	= 	McqChapter::where('subject_id', '=', $requestData['id'])->get();

					if($data)
					{
						$status = 1;
						$message = 'Record has been fetched successfully.';
					}
					else
					{
						$message = config('constants.server_error');
					}
				}
				else
				{
					$message = 'ID does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'ID does not exist, please check it once again.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message, 'data'=>$data));
		die;
	}

	public function addCkeditiorImage(Request $request)
	{
		echo "<pre>";
		print_r($_FILES);die;

		$status 			= 	0;
		$method 			= 	$request->method();

		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			echo "<pre>";
			print_r($requestData);die;

			if(isset($requestData['id']) && !empty($requestData['id']))
			{
			$recordExist = Country::where('id', $requestData['id'])->exists();

			if($recordExist)
			{
				// State functionality disabled - State model has been removed
				$data = [];
				$status = 0;
				$message = 'State functionality has been disabled - State model has been removed';
			}
			else
			{
				$message = 'ID does not exist, please check it once again.';
			}
		}
		else
		{
			$message = 'ID does not exist, please check it once again.';
		}
	}
	else
	{
		$message = config('constants.post_method');
	}
	echo json_encode(array('status'=>$status, 'message'=>$message, 'data'=>$data));
	die;
}

	public function gettemplates(Request $request){
		$id = $request->id;
		$template = \App\Models\EmailTemplate::find($id);
		if ($template) {
			echo json_encode(array('subject' => $template->subject, 'description' => $template->description));
		} else {
			echo json_encode(array('subject' => '', 'description' => ''));
		}
	}

	/**
	 * Get compose defaults for a client matter: first email template, dedicated checklist IDs, and macro values.
	 * Used to auto-select matter's first email and checklists when opening compose modal.
	 * macro_values enables replacement of {ClientID}, {ApplicantGivenNames}, {visa_apply}, etc. in First email template.
	 */
	public function getComposeDefaults(Request $request){
		$clientMatterId = $request->client_matter_id;
		if (!$clientMatterId) {
			return response()->json(['template' => null, 'checklist_ids' => [], 'macro_values' => null]);
		}
		$clientMatter = ClientMatter::find($clientMatterId);
		if (!$clientMatter || !$clientMatter->sel_matter_id) {
			return response()->json(['template' => null, 'checklist_ids' => [], 'macro_values' => null]);
		}
		$matterId = $clientMatter->sel_matter_id;
		$clientId = $clientMatter->client_id;

		// First Email template - one per matter
		$firstTemplate = \App\Models\EmailTemplate::forMatter($matterId)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->orderBy('id', 'asc')->first();

		// Additional matter templates - multiple per matter
		$otherTemplates = \App\Models\EmailTemplate::forMatter($matterId)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_OTHER)->orderBy('id', 'asc')->get();

		// Build full list: first email first, then other templates
		$allTemplates = [];
		if ($firstTemplate) {
			$allTemplates[] = ['id' => $firstTemplate->id, 'name' => $firstTemplate->name, 'subject' => $firstTemplate->subject, 'description' => $firstTemplate->description];
		}
		foreach ($otherTemplates as $t) {
			$allTemplates[] = ['id' => $t->id, 'name' => $t->name, 'subject' => $t->subject, 'description' => $t->description];
		}

		$checklistIds = \App\Models\UploadChecklist::where('matter_id', $matterId)->pluck('id')->toArray();

		// Build macro values for First email template replacement
		$macroValues = $this->getComposeMacroValues($clientId, $clientMatterId);

		return response()->json([
			'template' => $firstTemplate ? ['id' => $firstTemplate->id, 'name' => $firstTemplate->name, 'subject' => $firstTemplate->subject, 'description' => $firstTemplate->description] : null,
			'matter_templates' => $allTemplates,
			'checklist_ids' => $checklistIds,
			'macro_values' => $macroValues,
		]);
	}

	/**
	 * Get macro replacement values for a client matter (ClientID, ApplicantGivenNames, visa_apply, fees, etc.)
	 */
	protected function getComposeMacroValues($clientId, $clientMatterId)
	{
		$client = Admin::find($clientId);
		if (!$client) {
			return null;
		}

		$clientMatter = ClientMatter::find($clientMatterId);
		if (!$clientMatter) {
			return null;
		}

		$values = [
			'ClientID' => $client->client_id ?? '',
			'ApplicantGivenNames' => $client->first_name ?? '',
			'ApplicantSurname' => $client->last_name ?? '',
			'client_firstname' => ($client->first_name ?? '') ? ucfirst($client->first_name) : '',
			'client_reference' => $client->client_id ?? '',
			'visa_apply' => '',
			'Blocktotalfeesincltax' => '',
			'TotalDoHASurcharges' => '',
			'TotalEstimatedOthCosts' => '',
			'GrandTotalFeesAndCosts' => '',
			'PDF_url_for_sign' => '',
		];

		// Get matter/cost assignment info
		$matterInfo = null;
		$costAssignment = \App\Models\CostAssignmentForm::where('client_id', $clientId)->where('client_matter_id', $clientMatterId)->first();
		if ($costAssignment) {
			$matterInfo = DB::table('cost_assignment_forms')->where('client_id', $clientId)->where('client_matter_id', $clientMatterId)->first();
		}
		if (!$matterInfo && $clientMatter->sel_matter_id) {
			$clientMatterInfo = DB::table('client_matters')->select('sel_matter_id')->where('id', $clientMatterId)->first();
			if ($clientMatterInfo) {
				$matterInfo = DB::table('matters')->where('id', $clientMatterInfo->sel_matter_id)->first();
			}
		}

		// visa_apply must always come from matter title (matters table), not cost_assignment_forms
		if ($clientMatter->sel_matter_id) {
			$matterTitleRow = DB::table('matters')->select('title')->where('id', $clientMatter->sel_matter_id)->first();
			$values['visa_apply'] = $matterTitleRow->title ?? '';
		}

		if ($matterInfo) {

			$block1 = floatval($matterInfo->Block_1_Ex_Tax ?? 0);
			$block2 = floatval($matterInfo->Block_2_Ex_Tax ?? 0);
			$block3 = floatval($matterInfo->Block_3_Ex_Tax ?? 0);
			$blockTotal = $block1 + $block2 + $block3;
			$totalSurcharges = floatval($matterInfo->TotalDoHASurcharges ?? 0);
			$totalOther = floatval($matterInfo->additional_fee_1 ?? 0);
			// Department charges total (Dept. Charges on checklist) - from TotalDoHACharges when present (e.g. cost_assignment_forms), else sum Dept_* for compatibility
			$totalDepartmentCharges = floatval($matterInfo->TotalDoHACharges ?? 0);
			if ($totalDepartmentCharges == 0 && isset($costAssignment)) {
				$totalDepartmentCharges =
					floatval($matterInfo->Dept_Base_Application_Charge ?? 0) +
					floatval($matterInfo->Dept_Non_Internet_Application_Charge ?? 0) +
					floatval($matterInfo->Dept_Additional_Applicant_Charge_18_Plus ?? 0) +
					floatval($matterInfo->Dept_Additional_Applicant_Charge_Under_18 ?? 0) +
					floatval($matterInfo->Dept_Subsequent_Temp_Application_Charge ?? 0) +
					floatval($matterInfo->Dept_Second_VAC_Instalment_Charge_18_Plus ?? 0) +
					floatval($matterInfo->Dept_Second_VAC_Instalment_Under_18 ?? 0) +
					floatval($matterInfo->Dept_Nomination_Application_Charge ?? 0) +
					floatval($matterInfo->Dept_Sponsorship_Application_Charge ?? 0);
			}
			$grandTotal = $blockTotal + $totalDepartmentCharges + $totalSurcharges + $totalOther;

			$values['Blocktotalfeesincltax'] = number_format($blockTotal, 2, '.', '');
			// First email template: "Department fee, including the card Surcharge" row uses ${TotalDoHASurcharges} → send department total to match checklist
			$values['TotalDoHASurcharges'] = number_format($totalDepartmentCharges, 2, '.', '');
			// First email template: "Other Costs (estimated)" row uses ${TotalEstimatedOthCosts} → send surcharge to match checklist
			$values['TotalEstimatedOthCosts'] = number_format($totalSurcharges, 2, '.', '');
			$values['GrandTotalFeesAndCosts'] = number_format($grandTotal, 2, '.', '');
		}

		return $values;
	}

	/**
	 * Convert plain URLs in HTML content to clickable links (open in new tab, copyable).
	 * URL as link text makes it copyable. Skips URLs inside href="..." (preceded by space/> not ").
	 */
	protected function linkifyUrlsInHtml(string $html): string
	{
		if (empty(trim($html))) {
			return $html;
		}
		// Match URLs preceded by start, whitespace, or > (excludes href="url" where " is before url)
		$pattern = '#(^|[\s>])(https?://[^\s<>"\']+)#i';
		return preg_replace_callback($pattern, function ($m) {
			$prefix = $m[1];
			$url = $m[2];
			$link = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;word-break:break-all;">' . htmlspecialchars($url) . '</a>';
			return $prefix . $link;
		}, $html);
	}

    public function sendmail(Request $request){
		$requestData = $request->all();
		// Restore & in subject (front-end sends __AMP__ to avoid WAF 403 on special characters)
		$requestData['subject'] = str_replace('__AMP__', '&', $requestData['subject'] ?? '');
		//echo '<pre>'; print_r($requestData); die;

		// Gate on the associated client or lead record
		$associatedAdminId = $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
		if ($associatedAdminId) {
			$this->ensureCrmRecordAccess((int) $associatedAdminId);
		}

		$user_id = @Auth::user()->id;
		$reciept_id = null;
		$array = array();

		$obj = new \App\Models\EmailLog;
		$obj->user_id 		=  $user_id;
		$obj->from_mail 	=  $requestData['email_from'];
		// email_to / email_cc are Admin or Agent row IDs from the compose UI — store actual addresses
		$emailToList = isset($requestData['email_to']) && is_array($requestData['email_to'])
			? $requestData['email_to']
			: array_filter([(string) ($requestData['email_to'] ?? '')]);
		$resolvedTo = [];
		foreach ($emailToList as $recipientId) {
			if ($recipientId === '' || $recipientId === null) {
				continue;
			}
			if (($requestData['type'] ?? '') === 'agent') {
				$r = \App\Models\AgentDetails::where('id', $recipientId)->first();
				if ($r) {
					$em = $r->email ?: $r->business_email;
					if ($em) {
						$resolvedTo[] = $em;
					}
				}
			} else {
				$r = \App\Models\Admin::where('id', $recipientId)->first();
				if ($r && ! empty($r->email)) {
					$resolvedTo[] = $r->email;
				}
			}
		}
		$obj->to_mail = $resolvedTo !== []
			? implode(',', array_unique($resolvedTo))
			: implode(',', $emailToList);
		if (isset($requestData['email_cc']) && ! empty($requestData['email_cc'])) {
			$ccEmails = [];
			foreach ($requestData['email_cc'] as $ccId) {
				$ccRow = \App\Models\Admin::where('id', $ccId)->first();
				if ($ccRow && ! empty($ccRow->email)) {
					$ccEmails[] = $ccRow->email;
				}
			}
			$obj->cc = implode(',', array_unique($ccEmails));
		}
        $obj->template_id 	=  $requestData['template'] ?? null;
		$obj->reciept_id 	=  $reciept_id;
		$obj->subject		=  $requestData['subject'];
		if(isset($requestData['type'])){
		    $obj->type 			=  @$requestData['type'];
		}
		$obj->message		 =  $requestData['message'];
        $obj->mail_type		 =  $requestData['mail_type'] ?? 1;
        $obj->client_id		 =  $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
        $obj->client_matter_id	=  $requestData['compose_client_matter_id'] ?? null;

		$attachments = array();
		if(isset($requestData['checklistfile'])){
            if(!empty($requestData['checklistfile'])){
                $checklistfiles = $requestData['checklistfile'];
                $attachments = array();
                foreach($checklistfiles as $checklistfile){
                    $filechecklist =  \App\Models\UploadChecklist::where('id', $checklistfile)->first();
                    if($filechecklist){
                        $attachments[] = array('file_name' => $filechecklist->name,'file_url' => $filechecklist->file);
                    }
                }
                //$obj->attachments = json_encode($attachments);
            }
        }

        $attachments2 = array();
        if(isset($requestData['checklistfile_document'])){
            if(!empty($requestData['checklistfile_document'])){
                $checklistfiles_documents = $requestData['checklistfile_document'];
                $attachments2 = array();
                foreach($checklistfiles_documents as $checklistfile1){
                    $filechecklist_doc =  \App\Models\Document::where('id', $checklistfile1)->first();
                    if($filechecklist_doc){
                        if( $filechecklist_doc->doc_type == "education" || $filechecklist_doc->doc_type == "migration" ){
                            $attachments2[] = array('file_name' => $filechecklist_doc->name,'file_url' => $filechecklist_doc->file);
                        }
                        else if( $filechecklist_doc->doc_type == "documents")  {
                            $attachments2[] = array('file_name' => $filechecklist_doc->file_name,'file_url' => $filechecklist_doc->myfile);
                        }
                    }
                }
                //$obj->attachments = json_encode($attachments);
            }
        }

        $attachments = array_merge($attachments, $attachments2);
        if(!empty($attachments) && count($attachments) >0){
            $obj->attachments = json_encode($attachments);
        }

        $saved	=	$obj->save();
        $activityClientId = $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
        if ($activityClientId === null && ! empty($emailToList) && ($requestData['type'] ?? '') !== 'agent') {
            $activityClientId = (int) reset($emailToList);
        }
        if (isset($requestData['checklistfile'])) {
            if (! empty($requestData['checklistfile']) && $activityClientId) {
                $objs = new \App\Models\ActivitiesLog;
                $objs->client_id = $activityClientId;
                $objs->created_by = Auth::user()->id;
                $objs->subject = 'Checklist sent to client';
                $objs->task_status = 0;
                $objs->pin = 0;
                $objs->save();
            }
        }

        if (isset($requestData['checklistfile_document'])) {
            if (! empty($requestData['checklistfile_document']) && $activityClientId) {
                $objs = new \App\Models\ActivitiesLog;
                $objs->client_id = $activityClientId;
                $objs->created_by = Auth::user()->id;
                $objs->subject = 'Document Checklist sent to client';
                $objs->task_status = 0;
                $objs->pin = 0;
                $objs->save();
            }
        }

        // Visa sheet integration: when checklist sent, update the correct reference table per subclass (TR, Visitor, Student, PR, Employer Sponsored)
        $checklistWasSent = (!empty($requestData['checklistfile']) || !empty($requestData['checklistfile_document']));
        $clientMatterId = $requestData['compose_client_matter_id'] ?? null;
        $isLead = (($requestData['type'] ?? '') === 'lead');
        $leadId = $requestData['lead_id'] ?? ($requestData['client_id'] ?? null);
        $composeMatterId = $requestData['compose_matter_id'] ?? null;

        if ($checklistWasSent && $clientMatterId) {
            $clientMatter = ClientMatter::with('matter')->find($clientMatterId);
            if ($clientMatter) {
                $clientMatter->recordChecklistSent(Auth::user()->id);
            }
        } elseif ($checklistWasSent && $isLead && $leadId && $composeMatterId) {
            \App\Services\VisaSheetService::recordLeadChecklistSent((int) $leadId, (int) $composeMatterId, Auth::user()->id);
        }

		$subject = $requestData['subject'];
		$message = $requestData['message'];

		// Replace First email macros when matter context is present
		$clientMatterIdForMacros = $requestData['compose_client_matter_id'] ?? null;
		$clientIdForMacros = $requestData['client_id'] ?? null;
		if ($clientMatterIdForMacros && $clientIdForMacros) {
			$macroValues = $this->getComposeMacroValues($clientIdForMacros, $clientMatterIdForMacros);
		if ($macroValues) {
			foreach ($macroValues as $key => $val) {
				if ((string)$val === '' || $key === 'PDF_url_for_sign') continue;
				$subject = str_replace('{' . $key . '}', $val, $subject);
				$subject = str_replace('${' . $key . '}', $val, $subject);
				$message = str_replace('{' . $key . '}', $val, $message);
				$message = str_replace('${' . $key . '}', $val, $message);
			}
		}
		}
		// Convert plain URLs to clickable links (open in new tab, copyable)
		$message = $this->linkifyUrlsInHtml($message);

		foreach($requestData['email_to'] as $l){
			if(@$requestData['type'] == 'agent'){
				$client = \App\Models\AgentDetails::Where('id', $l)->first();
			    $subject = str_replace('{Client First Name}',$client->full_name, $subject);
			    $message = str_replace('{Client First Name}',$client->full_name, $message);
			}else{
				$client = \App\Models\Admin::Where('id', $l)->first();
			    $subject = str_replace('{Client First Name}',$client->first_name, $subject);
			    $message = str_replace('{Client First Name}',$client->first_name, $message);
			}

			$message = str_replace('{Client Assignee Name}',$client->first_name, $message);
			$message = str_replace('{Company Name}', optional(Auth::user())->company_name ?? '', $message);
			$ccarray = array();
			if(isset($requestData['email_cc']) && !empty($requestData['email_cc'])){
				foreach($requestData['email_cc'] as $cc){
					$clientcc = \App\Models\Admin::Where('id', $cc)->first();
					$ccarray[] = $clientcc;
				}
			}

			if(isset($requestData['checklistfile'])){
    		    if(!empty($requestData['checklistfile'])){
    		       $checklistfiles = $requestData['checklistfile'];
    		        foreach($checklistfiles as $checklistfile){
    		           $filechecklist =  \App\Models\UploadChecklist::where('id', $checklistfile)->first();
    		           if($filechecklist){
    		            $array['files'][] =  public_path() . '/' .'checklists/'.$filechecklist->file;
    		           }
    		        }
    		    }
		    }

            if(isset($requestData['checklistfile_document'])){
                if(!empty($requestData['checklistfile_document'])){
                    $checklistfiles_documents = $requestData['checklistfile_document'];
                    foreach($checklistfiles_documents as $checklistfile1){
                        $filechecklist_doc =  \App\Models\Document::where('id', $checklistfile1)->first();
                        if($filechecklist_doc){
                            if( $filechecklist_doc->doc_type == "education" || $filechecklist_doc->doc_type == "migration" ){
                                $array['files'][] =  public_path() . '/' .'img/documents/'.$filechecklist_doc->myfile;
                            }
                            else if( $filechecklist_doc->doc_type == "documents") {
                                $fileUrl = $filechecklist_doc->myfile; // AWS S3 link

                                // Check if it's a URL
                                if(filter_var($fileUrl, FILTER_VALIDATE_URL)){
                                    // Download and save to a temporary location
                                    $tempPath = sys_get_temp_dir() . '/' . basename($fileUrl);
                                    file_put_contents($tempPath, file_get_contents($fileUrl));
                                    $array['files'][] = $tempPath; // Attach the temp file
                                } else {
                                    $array['files'][] = $fileUrl; // Local file
                                }
                            }
                        }
                    }
                }
            }
            //echo "<pre>array=";print_r($array);die;

		    /*if($request->hasfile('attach'))
            {
                 $array['filesatta'][] =  $request->attach;
            }*/

            // Process Uploaded Files
            if ($request->hasFile('attach')) {
                foreach ($request->file('attach') as $file1) {
                    $array['filesatta'][] =  $file1;
                }
            }

            //dd($client->email,  $requestData['email_from']);
            //$this->send_compose_template($client->email, $subject, $requestData['email_from'], $message, '', $array,@$ccarray);

            try {
                $attachments = [];
                //dd($array['filesatta']);
                if(isset($array['files'])){
                    $attachments = array_merge($attachments, $array['files']);
                }

                if(isset($array['filesatta'])){
                    foreach($array['filesatta'] as $file) {
                        $filename = time().'_'.$file->getClientOriginalName(); // Unique filename
                        $filePath = storage_path('app/uploads/'.$filename); // Save in storage/uploads folder

                        // Move the file to storage folder
                        $file->move(storage_path('app/uploads'), $filename);

                        // Add saved file path to attachments
                        $attachments[] = $filePath;
                    }
                }

                $ccarray = [];
                if(isset($requestData['email_cc']) && !empty($requestData['email_cc'])){
                    foreach($requestData['email_cc'] as $cc){
                        $clientcc = \App\Models\Admin::Where('id', $cc)->first();
                        if($clientcc) {
                            $ccarray[] = $clientcc->email;
                        }
                    }
                }
                //dd($attachments);
                $this->emailService->sendEmail(
                    'emails.template',
                    ['content' => $message],
                    $client->email,
                    $subject,
                    $requestData['email_from'],
                    $attachments,
                    $ccarray
                );

                // Store full email to S3 for archival (HTML snapshot + attachments)
                try {
                    $attachmentTuples = [];
                    foreach ($attachments as $p) {
                        if (is_string($p) && file_exists($p)) {
                            $attachmentTuples[] = ['path' => $p, 'name' => basename($p)];
                        }
                    }
                    $this->crmSentEmailS3Service->storeToS3($obj, $subject, $message, $attachmentTuples);
                } catch (\Exception $s3Ex) {
                    \Log::warning('CRM sent email S3 storage failed (email still sent)', ['error' => $s3Ex->getMessage()]);
                }

                // Return JSON response for AJAX requests, redirect for regular form submissions
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => true,
                        'success' => true,
                        'message' => 'Email sent successfully!'
                    ]);
                }
                return redirect()->back()->with('success', 'Email sent successfully!');
            } catch (\Exception $e) {
                // Return JSON response for AJAX requests, redirect for regular form submissions
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => false,
                        'success' => false,
                        'message' => 'Failed to send email: ' . $e->getMessage()
                    ], 422);
                }
                return redirect()->back()->with('error', 'Failed to send email: ' . $e->getMessage())->withInput();
            }
        }
        if(!empty($array['file'])){
            unset($array['file']);
        }
        if(!$saved) {
            // Return JSON response for AJAX requests, redirect for regular form submissions
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => config('constants.server_error')
                ], 500);
            }
            return redirect()->back()->with('error', config('constants.server_error'));
        } else {
            // Return JSON response for AJAX requests, redirect for regular form submissions
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => true,
                    'success' => true,
                    'message' => 'Email Sent Successfully'
                ]);
            }
            return redirect()->back()->with('success', 'Email Sent Successfully');
        }
	}

		public function getpartnerajax(Request $request){
	    // Partner functionality removed - no partners available
	    $agents = array();
		echo json_encode($agents);
	}

	public function getassigneeajax(Request $request){
	    \Log::info('📋 getassigneeajax called', [
	        'search' => $request->likevalue,
	        'user_id' => Auth::id(),
	    ]);
	    
	    try {
	        $squery = $request->likevalue;
	        
	        $query = \App\Models\Staff::query()
                ->where('status', 1);  // Only active staff
                
            // Apply search filter if provided
            if (!empty($squery)) {
                $query->where(function($q) use ($squery) {
                    $squeryLower = strtolower($squery);
                    $q->whereRaw('LOWER(email) LIKE ?', ['%'.$squeryLower.'%'])
                      ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$squeryLower.'%'])
                      ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$squeryLower.'%'])
                      ->orWhereRaw('LOWER(phone) LIKE ?', ['%'.$squeryLower.'%'])
                      ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$squeryLower.'%']);
                });
            }
            
            $fetchedData = $query->orderBy('first_name')->orderBy('last_name')->get();

    		$agents = array();
    		foreach($fetchedData as $list){
    			$agents[] = array(
    				'id' => $list->id,
    				'agent_id' => $list->first_name.' '.$list->last_name,
    				'assignee' => $list->first_name.' '.$list->last_name,
    			);
    		}
    
    		\Log::info('✅ getassigneeajax success', [
    		    'count' => count($agents),
    		    'sample' => array_slice($agents, 0, 3),
    		]);
    
    		return response()->json($agents);
	    } catch (\Exception $e) {
	        \Log::error('❌ getassigneeajax failed', [
	            'error' => $e->getMessage(),
	            'trace' => $e->getTraceAsString(),
	        ]);
	        
	        return response()->json([
	            'error' => 'Failed to load staff list',
	            'message' => $e->getMessage(),
	        ], 500);
	    }
	}


    public function checkclientexist(Request $request){
        if($request->type == 'email'){
         $clientexists = \App\Models\Admin::where('email', $request->vl)->whereIn('type', ['client', 'lead'])->exists();
            if($clientexists){
                echo 1;
            }else{
                echo 0;
            }
        }else if($request->type == 'clientid'){
         $clientexists = \App\Models\Admin::where('client_id', $request->vl)->whereIn('type', ['client', 'lead'])->exists();
            if($clientexists){
                echo 1;
            }else{
                echo 0;
            }
        }else{
            $clientexists = \App\Models\Admin::where('phone', $request->vl)->whereIn('type', ['client', 'lead'])->exists();
            if($clientexists){
                echo 1;
            }else{
                echo 0;
            }
        }
    }

	public function allnotification(Request $request){
		$lists = \App\Models\Notification::where('receiver_id', Auth::user()->id)->orderby('created_at','DESC')->paginate(20);
		// Fix URLs for notifications that point to non-existent or wrong routes
		$lists->getCollection()->transform(function ($notification) {
			// Message notifications: /messages (404) -> client detail + client portal tab
			if ($notification->notification_type === 'message' && ($notification->url === '/messages' || str_starts_with($notification->url ?? '', '/messages'))) {
				$clientMatter = \DB::table('client_matters')->where('id', $notification->module_id)->first();
				if ($clientMatter) {
					$path = '/clients/detail/' . base64_encode(convert_uuencode($clientMatter->client_id));
					if (!empty($clientMatter->client_unique_matter_no)) {
						$path .= '/' . $clientMatter->client_unique_matter_no;
					}
					$notification->url = url($path . '/client_portal');
				}
			}
			// Broadcast notifications: /broadcasts/{uuid} (404) -> manage page with batch param
			if ($notification->notification_type === 'broadcast' && str_starts_with($notification->url ?? '', '/broadcasts/')) {
				$batchUuid = \Illuminate\Support\Str::afterLast($notification->url ?? '', '/');
				if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $batchUuid)) {
					$notification->url = url('/notifications/broadcasts/manage?batch=' . urlencode($batchUuid));
				}
			}
			return $notification;
		});
		return view('crm.notifications', compact(['lists']));
	}

    // Dashboard methods moved to DashboardController

	//Get matter templates
	public function getmattertemplates(Request $request){
		$id = $request->id;
		$template = \App\Models\EmailTemplate::find($id);
		if ($template) {
			echo json_encode(array('subject' => $template->subject, 'description' => $template->description));
		} else {
			echo json_encode(array('subject' => '', 'description' => ''));
		}
	}

    // Column preferences method moved to DashboardController

    /**
     * Get office visit notifications (optimized - no N+1 queries)
     */
    public function fetchOfficeVisitNotifications(Request $request)
    {
        // Fetch notifications with sender relationship eager loaded
        $notifications = \App\Models\Notification::with(['sender:id,first_name,last_name'])
            ->where('receiver_id', Auth::id())
            ->where('notification_type', 'officevisit')
            ->where('receiver_status', 0)
            ->orderBy('created_at', 'DESC')
            ->get();

        if ($notifications->isEmpty()) {
            return response()->json(['notifications' => [], 'count' => 0]);
        }

        // Batch-load all checkin logs (eliminates N+1)
        $checkinLogIds = $notifications->pluck('module_id')->filter()->unique();
        $checkinLogs = \App\Models\CheckinLog::whereIn('id', $checkinLogIds)
            ->where('status', 0)
            ->get()
            ->keyBy('id');

        // If no active checkin logs, return empty
        if ($checkinLogs->isEmpty()) {
            return response()->json(['notifications' => [], 'count' => 0]);
        }

        $clientIds = $checkinLogs->pluck('client_id')->filter()->unique()->values();
        $contacts = $clientIds->isNotEmpty()
            ? \App\Models\Admin::whereIn('type', ['client', 'lead'])->whereIn('id', $clientIds)->get()->keyBy('id')
            : collect();

        // Build response data
        $data = [];
        foreach ($notifications as $notification) {
            $checkinLog = $checkinLogs->get($notification->module_id);
            
            if (!$checkinLog) {
                continue;
            }

            $preloaded = $checkinLog->client_id ? $contacts->get($checkinLog->client_id) : null;

            $data[] = [
                'id' => $notification->id,
                'checkin_id' => $checkinLog->id,
                'message' => $notification->message,
                'sender_name' => $notification->sender 
                    ? $notification->sender->first_name . ' ' . $notification->sender->last_name 
                    : 'System',
                'client_name' => $checkinLog->contactDisplayLabel($preloaded),
                'visit_purpose' => $checkinLog->visit_purpose,
                'created_at' => $notification->created_at->format('d/m/Y h:i A'),
                'url' => $notification->url
            ];
        }

        return response()->json(['notifications' => $data, 'count' => count($data)]);
    }

    /**
     * Get in-person waiting count
     */
    public function fetchInPersonWaitingCount(Request $request)
    {
        $InPersonwaitingCount = \App\Models\CheckinLog::inPersonWaitingCountForViewer();

        return response()->json(['InPersonwaitingCount' => $InPersonwaitingCount]);
    }

    /**
     * Get total activity count
     */
    public function fetchTotalActivityCount(Request $request)
    {
        if (Auth::user()->role == 1) {
            $assigneesCount = \App\Models\Note::where('type', 'client')
                ->whereNotNull('client_id')
                ->where('is_action', 1)
                ->where('status', 0)
                ->count();
        } else {
            $assigneesCount = \App\Models\Note::where('assigned_to', Auth::user()->id)
                ->where('type', 'client')
                ->where('is_action', 1)
                ->where('status', 0)
                ->count();
        }
        
        return response()->json(['assigneesCount' => $assigneesCount]);
    }

    /**
     * Get visa expiry messages
     */
    public function fetchvisaexpirymessages(Request $request)
    {
        $this->validate($request, [
            'client_id' => 'required|integer'
        ]);

        $this->ensureCrmRecordAccess((int) $request->client_id);

        $visaInfo = \App\Models\ClientVisaCountry::where('client_id', $request->client_id)
            ->latest('id')
            ->first();

        if (!$visaInfo || !$visaInfo->visa_expiry_date) {
            return '';
        }

        $visaExpiredAt = \Carbon\Carbon::parse($visaInfo->visa_expiry_date);
        $today = \Carbon\Carbon::now();
        $sevenDaysFromNow = \Carbon\Carbon::now()->addDays(7);

        if ($visaExpiredAt->lt($today)) {
            return "Your visa has expired. Please contact us immediately.";
        } elseif ($visaExpiredAt->gte($today) && $visaExpiredAt->lte($sevenDaysFromNow)) {
            $daysRemaining = $visaExpiredAt->diffInDays($today);
            return "Your visa is expiring in next $daysRemaining day" . ($daysRemaining == 1 ? '' : 's');
        }

        return '';
    }

    /**
     * Mark notification as seen
     */
    public function markNotificationSeen(Request $request)
    {
        $notification = \App\Models\Notification::find($request->notification_id);
        
        if (!$notification || $notification->receiver_id != Auth::id()) {
            return response()->json(['status' => 'error']);
        }

        $notification->receiver_status = 1;
        $notification->save();

        return response()->json(['status' => 'success']);
    }
}