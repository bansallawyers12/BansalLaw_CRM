<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

use App\Mail\CommonMail;
use App\Services\MailRoutingService;

use App\Models\UserRole;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

	public function __construct()
    {
        // Share safe defaults instead of WebsiteSetting
        $siteData = (object) [
            'phone' => env('APP_PHONE', ''),
            'ofc_timing' => env('APP_OFFICE_TIMING', ''),
            'email' => env('APP_EMAIL', ''),
            'logo' => env('APP_LOGO', 'logo.png'),
        ];
        View::share('siteData', $siteData);
    }

	public function decodeString($string = NULL)
	{
		if ( base64_encode(base64_decode($string, true)) === $string)
		{
			// First decode base64, then decode uuencode
			$base64Decoded = base64_decode($string);
			try {
				$uuDecoded = @convert_uudecode($base64Decoded);
			} catch (\ValueError $e) {
				$uuDecoded = false;
			}
			return $uuDecoded;
		}
		else
		{
			return false;
		}
	}

	public function uploadFile($file = NULL, $filePath = NULL)
	{
		$fileName = $file->getClientOriginalName();
		$explodeFileName = explode('.', $fileName);
		$newFileName = $explodeFileName[0];
		$ext = $file->getClientOriginalExtension();
		$newFileName=str_replace(' ', '_', $newFileName);
		$newFileName = $newFileName.'.'.$ext;

		if($file->move($filePath, $newFileName))
		{
			return $newFileName;
		}
	}

	protected function crmMailRouting(): MailRoutingService
	{
		return app(MailRoutingService::class);
	}

	protected function send_compose_template($to = null, $subject = null, $sender = null, $content = null, $sendername = null, $array = array(), $cc = array())
	{

		try {
			$explodeTo = explode(';', $to);//for multiple and single to
			$q = $this->crmMailRouting()->mailer($sender)->to($explodeTo);
			if(!empty($cc)){
				$q->cc($cc);
			}
			$q->send(new CommonMail($content, $subject, $sender, $sendername, $array));
			
			return true;
		} catch (\Exception $e) {
			Log::error('Email sending failed: ' . $e->getMessage());
			return false;
		}

	}

	public function checkAuthorizationAction($controller = NULL, $action = NULL, $role = NULL)
	{
		// Super Admin (role 1) — unrestricted
		if ($role == 1) {
			return null;
		}

		// CRM access approvers get the same unrestricted admin console access as Super Admin
		$actor = Auth::user();
		if ($actor instanceof \App\Models\Staff && app(\App\Services\CrmAccess\CrmAccessService::class)->hasAdminConsoleLikeSuperAdminAccess($actor)) {
			return null;
		}

		if (!$role) {
			return true;
		}

		$userrole = UserRole::find($role);
		if (!$userrole) {
			return true;
		}

		$module_access = $userrole->module_access;
		if (empty($module_access)) {
			return true;
		}

		$decoded = json_decode(trim((string) $module_access), true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
			return true;
		}

		$moduleKeysToCheck = match ($controller) {
			'user_management' => [3, 4, '3', '4', 'user_management'],
			'user_role' => [6, '6', 'user_role'],
			'branch_management' => [1, 2, '1', '2', 'branch_management'],
			'matter_management' => [34, 40, 41, '34', '40', '41', 'matter_management'],
			default => [$controller],
		};

		$hasAccess = false;
		if (array_is_list($decoded)) {
			foreach ($moduleKeysToCheck as $key) {
				if (in_array($key, $decoded, false) || in_array((string)$key, $decoded, true)) {
					$hasAccess = true;
					break;
				}
			}
		} else {
			foreach ($moduleKeysToCheck as $key) {
				if (array_key_exists($key, $decoded) || array_key_exists((string)$key, $decoded)) {
					$hasAccess = true;
					break;
				}
			}
		}

		return $hasAccess ? null : true;
	}

	public static function time_elapsed_string($datetime, $full = false) {
    $now = new \DateTime;
    $ago = new \DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = (int) floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);

    $values = array(
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    );

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if (!empty($values[$k])) {
            $v = $values[$k] . ' ' . $v . ($values[$k] > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
}
