<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;

use App\Logging\InboxSyncLogger;
use App\Models\Admin;
use App\Models\Email;
use App\Models\Staff;
use App\Services\EmailSync\InboxSyncMasterControl;
use App\Services\EmailSync\IncomingEmailSyncService;
use App\Services\StaffMailboxService;

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

		$accountsQuery = Email::query()->orderBy('email');
		IncomingEmailSyncService::applyMailboxHasZohoPasswordScope($accountsQuery);
		$accountsQuery->where(function ($query) {
			$query->whereNull('mail_provider')
				->orWhere('mail_provider', '')
				->orWhere('mail_provider', 'zoho');
		});
		$accounts = $accountsQuery->get();

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

			$syncLabel = 'Not synced yet';
			if ($account->last_synced_at) {
				$syncLabel = $account->last_synced_at->timezone(config('app.timezone'))->format('d/m/Y h:i a');
				if (! empty($account->last_sync_error)) {
					$syncLabel .= ' (error)';
				}
			}

			return (object) [
				'id' => (int) $account->id,
				'email' => $account->email,
				'display_name' => $account->display_name ?? '',
				'email_signature' => $account->email_signature ?? '',
				'status' => (int) $account->status,
				'user_sharing' => implode(', ', $userNames),
				'sync_enabled' => (int) ($account->sync_enabled ?? 1),
				'last_synced_at' => $syncLabel,
				'last_sync_error' => $account->last_sync_error ?? '',
			];
		})->all();

		$totalData = count($lists);

		$staff = Auth::guard('admin')->user();
		$canControlInboxSyncMaster = $staff instanceof Staff && InboxSyncMasterControl::canControl($staff);
		$canPauseMailboxInboxSync = $staff instanceof Staff && $staff->canPauseMailboxInboxSync();
		$inboxSyncMaster = InboxSyncMasterControl::statusPayload();

		return view('AdminConsole.features.emails.index', compact([
			'lists',
			'totalData',
			'canControlInboxSyncMaster',
			'canPauseMailboxInboxSync',
			'inboxSyncMaster',
		]));

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
			$requestData = 	$request->all();
			$provider = $this->normalizeMailProvider(@$requestData['mail_provider'] ?: 'zoho');
			$rules = ['email' => 'required|max:255|unique:emails'];
			if ($provider === 'zoho') {
				$rules['password'] = 'required|string';
			}
			$this->validate($request, $rules);

			$obj		 = 	new Email;
			$obj->email	 =	@$requestData['email'];
			$obj->email_signature	=	@$requestData['email_signature'];
			$obj->display_name	=	@$requestData['display_name'];
			$obj->mail_provider = $provider;
			$obj->smtp_host = @$requestData['smtp_host'] ?: 'smtp.zoho.com';
			$obj->smtp_port = @$requestData['smtp_port'] ?: 587;
			$obj->smtp_encryption = @$requestData['smtp_encryption'] ?: 'tls';
			if (! empty($requestData['password'])) {
				$obj->password = app(StaffMailboxService::class)->encryptMailboxPassword($requestData['password']);
			}
			$obj->status	=	! empty($requestData['status']) ? 1 : 0;
			$obj->sync_enabled = ! empty($requestData['sync_enabled']) ? 1 : 0;
			$obj->sync_sent_enabled = ! empty($requestData['sync_sent_enabled']) ? 1 : 0;
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
			$obj->password = app(StaffMailboxService::class)->encryptMailboxPassword($requestData['password']);
		}
		$obj->status = ! empty($requestData['status']) ? 1 : 0;
		$obj->sync_enabled = ! empty($requestData['sync_enabled']) ? 1 : 0;
		$obj->sync_sent_enabled = ! empty($requestData['sync_sent_enabled']) ? 1 : 0;
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

	public function syncNow(Request $request, \App\Services\EmailSync\IncomingEmailSyncService $syncService)
	{
		if (InboxSyncMasterControl::isDisabled()) {
			$message = InboxSyncMasterControl::disabledMessage();
			if ($request->expectsJson()) {
				return response()->json(['success' => false, 'message' => $message], 403);
			}

			return redirect()->route('adminconsole.features.emails.index')->with('error', $message);
		}

		$email = trim((string) $request->input('email', ''));
		$summary = $syncService->syncAll($email !== '' ? $email : null);

		if ($request->expectsJson()) {
			return response()->json($summary);
		}

		$message = sprintf(
			'Sync complete. Imported: %d, Skipped: %d, Failed: %d',
			(int) ($summary['total_imported'] ?? 0),
			(int) ($summary['total_skipped'] ?? 0),
			(int) ($summary['total_failed'] ?? 0)
		);

		return redirect()->route('adminconsole.features.emails.index')->with('success', $message);
	}

	/**
	 * Super Admin only: turn global Zoho inbox sync (cron + manual + Unassigned) on/off.
	 * Takes effect immediately without redeploy or config:cache.
	 */
	public function updateInboxSyncMaster(Request $request)
	{
		$staff = Auth::guard('admin')->user();
		if (! InboxSyncMasterControl::canControl($staff instanceof Staff ? $staff : null)) {
			if ($request->expectsJson()) {
				return response()->json([
					'success' => false,
					'message' => 'Only Super Admin can change the inbox sync master switch.',
				], 403);
			}

			return redirect()->route('adminconsole.features.emails.index')
				->with('error', 'Only Super Admin can change the inbox sync master switch.');
		}

		$validated = $request->validate([
			'enabled' => 'required|boolean',
		]);

		$enabled = (bool) $validated['enabled'];
		InboxSyncMasterControl::setEnabled($enabled, $staff instanceof Staff ? $staff : null);

		$message = $enabled
			? 'Inbox auto-sync is ON. Cron and manual sync will run again; Unassigned Mail is available in the menu.'
			: 'Inbox auto-sync is OFF. Cron, manual sync, and Unassigned Mail are stopped until Super Admin turns it back on.';

		if ($request->expectsJson()) {
			return response()->json([
				'success' => true,
				'message' => $message,
				'status' => InboxSyncMasterControl::statusPayload(),
			]);
		}

		return redirect()->route('adminconsole.features.emails.index')->with('success', $message);
	}

	/**
	 * Pause or resume automatic IMAP sync for one mailbox.
	 */
	public function toggleMailboxInboxSync(Request $request)
	{
		$staff = Auth::guard('admin')->user();
		if (! ($staff instanceof Staff) || ! $staff->canPauseMailboxInboxSync()) {
			$message = 'You do not have permission to pause or start inbox sync for a mailbox.';
			if ($request->expectsJson()) {
				return response()->json(['success' => false, 'message' => $message], 403);
			}

			return redirect()->route('adminconsole.features.emails.index')->with('error', $message);
		}

		$validated = $request->validate([
			'email' => 'required|string|max:255',
			'sync_enabled' => 'required|boolean',
		]);

		$address = strtolower(trim((string) $validated['email']));
		$account = Email::query()->whereRaw('LOWER(email) = ?', [$address])->first();
		if (! $account) {
			$message = 'Email account not found.';
			if ($request->expectsJson()) {
				return response()->json(['success' => false, 'message' => $message], 404);
			}

			return redirect()->route('adminconsole.features.emails.index')->with('error', $message);
		}

		$enabled = (bool) $validated['sync_enabled'];
		$account->sync_enabled = $enabled ? 1 : 0;
		$account->save();

		InboxSyncLogger::info($enabled ? 'Mailbox inbox sync started' : 'Mailbox inbox sync paused', [
			'mailbox' => $account->email,
			'staff_id' => $staff->id,
			'sync_enabled' => $enabled,
		]);

		$message = $enabled
			? 'Inbox sync is ON for ' . $account->email . '.'
			: 'Inbox sync is paused for ' . $account->email . '. Cron will skip this mailbox until it is started again.';

		if ($request->expectsJson()) {
			return response()->json([
				'success' => true,
				'message' => $message,
				'sync_enabled' => $enabled,
			]);
		}

		return redirect()->route('adminconsole.features.emails.index')->with('success', $message);
	}
}


