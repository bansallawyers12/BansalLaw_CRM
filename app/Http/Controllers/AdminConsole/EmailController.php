<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;

use App\Models\Admin;
use App\Models\Email;
use App\Models\Staff;

use Auth;

class EmailController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

	/**
     * All Vendors.
     *
     * @return \Illuminate\Http\Response
     */
	public function index(Request $request)
	{
		//check authorization start

			/* if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			} */
		//check authorization end

		$accounts = Email::orderBy('email')->get();

		$staffNames = Staff::where('status', 1)
			->get()
			->keyBy('id')
			->map(function ($staff) {
				return trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''));
			});

		$lists = $accounts->map(function (Email $account) use ($staffNames) {
			$userNames = [];
			$userIds = json_decode($account->user_id ?? '[]', true);
			foreach ((array) $userIds as $userId) {
				$name = $staffNames->get((int) $userId);
				if (! empty($name)) {
					$userNames[] = $name;
				}
			}

			return (object) [
				'email' => $account->email,
				'display_name' => $account->display_name ?? '',
				'email_signature' => $account->email_signature ?? '',
				'status' => (int) $account->status,
				'user_sharing' => implode(', ', $userNames),
			];
		})->all();

		$totalData = count($lists);

		return view('AdminConsole.features.emails.index', compact(['lists', 'totalData']));

		//return view('AdminConsole\.features\.producttype.index');
	}

	public function create(Request $request)
	{
		//check authorization end
		//return view('AdminConsole\.system\.users\.create',compact(['usertype']));

		return view('AdminConsole.features.emails.create');
	}

	public function store(Request $request)
	{
		//check authorization end
		if ($request->isMethod('post'))
		{
			$this->validate($request, ['email' => 'required|max:255|unique:emails']);

			$requestData = 	$request->all();
            $obj		 = 	new Email;
			$obj->email	 =	@$requestData['email'];
			$obj->email_signature	=	@$requestData['email_signature'];
			$obj->display_name	=	@$requestData['display_name'];
			$obj->mail_provider = $this->normalizeMailProvider(@$requestData['mail_provider'] ?: 'zoho');
			$obj->smtp_host = @$requestData['smtp_host'] ?: 'smtp.zoho.com';
			$obj->smtp_port = @$requestData['smtp_port'] ?: 587;
			$obj->smtp_encryption = @$requestData['smtp_encryption'] ?: 'tls';
			if (! empty($requestData['password'])) {
				$obj->password = $requestData['password'];
			}
			$obj->status	=	! empty($requestData['status']) ? 1 : 0;
			$obj->user_id	=	json_encode(@$requestData['users']);
            $saved			=	$obj->save();

			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return redirect()->route('adminconsole.features.emails.index')->with('success', 'Email Added Successfully');
			}
		}

		return view('AdminConsole.features.emails.create');
	}

	/**
	 * Show the form for editing the specified email.
	 */
	public function edit($id)
	{
		//check authorization end

		if(isset($id) && !empty($id))
		{
			$id = $this->decodeString($id);
			if(Email::where('id', '=', $id)->exists())
			{
				$fetchedData = Email::find($id);
				return view('AdminConsole.features.emails.edit', compact(['fetchedData']));
			}
			else
			{
				return redirect()->route('adminconsole.features.emails.index')->with('error', 'Email Not Exist');
			}
		}
		else
		{
			return redirect()->route('adminconsole.features.emails.index')->with('error', config('constants.unauthorized'));
		}
	}

	/**
	 * Update the specified email in storage.
	 */
	public function update(Request $request, $id)
	{
		//check authorization end

		$requestData = $request->all();
		$this->validate($request, ['email' => 'required|max:255|unique:emails,email,'.$id]);
		
		$obj = Email::find($id);
		if (!$obj) {
			return redirect()->route('adminconsole.features.emails.index')->with('error', 'Email Not Found');
		}
		
		$obj->email = @$requestData['email'];
		$obj->email_signature = @$requestData['email_signature'];
		$obj->display_name = @$requestData['display_name'];
		$obj->mail_provider = $this->normalizeMailProvider(@$requestData['mail_provider'] ?: 'zoho');
		$obj->smtp_host = @$requestData['smtp_host'] ?: 'smtp.zoho.com';
		$obj->smtp_port = @$requestData['smtp_port'] ?: 587;
		$obj->smtp_encryption = @$requestData['smtp_encryption'] ?: 'tls';
		if (! empty($requestData['password'])) {
			$obj->password = $requestData['password'];
		}
		$obj->status = ! empty($requestData['status']) ? 1 : 0;
		$obj->user_id = json_encode(@$requestData['users']);
		$saved = $obj->save();

		if(!$saved)
		{
			return redirect()->back()->with('error', config('constants.server_error'));
		}
		else
		{
			return redirect()->route('adminconsole.features.emails.index')->with('success', 'Email Updated Successfully');
		}
	}

	private function normalizeMailProvider(?string $provider): string
	{
		$provider = strtolower(trim((string) $provider));

		if ($provider === 'sendgrid') {
			return 'ses';
		}

		return in_array($provider, ['ses', 'zoho'], true) ? $provider : 'zoho';
	}
}


