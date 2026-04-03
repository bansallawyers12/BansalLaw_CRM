<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

use App\Mail\CommonMail;
use App\Mail\InvoiceEmailManager;
use App\Mail\MultipleattachmentEmailManager;

use App\Models\UserRole;
// use App\Models\WebsiteSetting; // removed website settings dependency

//use Mail;
use Illuminate\Support\Facades\Mail;

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
        //$this->middleware('guest:admin')->except('logout');
	//	exec('php public_html/development/artisan view:clear');
    }


	public function generateRandomString($length = 10)
	{
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++)
			{
				$randomString .= $characters[rand(0, $charactersLength - 1)];
			}
		return $randomString;
	}

	public function encodeString($string = NULL)
	{
		return base64_encode(convert_uuencode($string));
	}

	public function decodeString($string = NULL)
	{
		if ( base64_encode(base64_decode($string, true)) === $string)
		{
			// First decode base64, then decode uuencode
			$base64Decoded = base64_decode($string);
			$uuDecoded = convert_uudecode($base64Decoded);
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
		//$newFileName = $explodeFileName[0].'_'.$this->generateRandomString(10);
		$newFileName = $explodeFileName[0];
		$ext = $file->getClientOriginalExtension();
		$newFileName=str_replace(' ', '_', $newFileName);
		$newFileName = $newFileName.'.'.$ext;

		if($file->move($filePath, $newFileName))
		{
			return $newFileName;
		}
	}

	public function uploadrenameFile($file = NULL, $filePath = NULL)
	{
		$fileName = $file->getClientOriginalName();
		$explodeFileName = explode('.', $fileName);
		$newFileName = $explodeFileName[0].'_'.$this->generateRandomString(10);

		$ext = $file->getClientOriginalExtension();
		$newFileName=str_replace(' ', '_', $newFileName);
		$newFileName = $newFileName.'.'.$ext;

		if($file->move($filePath, $newFileName))
		{
			return $newFileName;
		}
	}

	public function unlinkFile($file = NULL, $filePath = NULL)
	{
		$unlinkFiles = $filePath.'/'.$file;
		if(file_exists($unlinkFiles) && is_file($unlinkFiles))
			{
				unlink($unlinkFiles);
			}
	}

	protected function send_email_template($replace = array(), $replace_with = array(), $alias = null, $to = null, $subject = null, $sender = null, $sendername = null)
	{
		// email_templates table has been deleted - using fallback content
		$email_template	= 	DB::table('email_templates')->where('alias', $alias)->first();
		if(!$email_template) {
			Log::warning('Email template not found for alias: ' . $alias . ' - email_templates table has been deleted');
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
		$explodeTo = explode(';', $to);//for multiple and single to

		try {
			Mail::mailer('sendgrid')
				->to($explodeTo)
				->send(new CommonMail($emailContent, $subject, $sender, $sendername, []));

			return true;
		} catch (\Exception $e) {
			Log::error('Email sending failed: ' . $e->getMessage());
			return false;
		}

	}

	protected function send_compose_template($to = null, $subject = null, $sender = null,$content, $sendername, $array = array(), $cc = array())
	{

		try {
			$explodeTo = explode(';', $to);//for multiple and single to
			$q = Mail::mailer('sendgrid')->to($explodeTo);
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
	protected function send_attachment_email_template($replace = array(), $replace_with = array(), $alias = null, $to = null, $subject = null, $sender = null,$invoicearray)
	{
		// email_templates table has been deleted - using fallback content
		$email_template	= 	DB::table('email_templates')->where('alias', $alias)->first();
		if(!$email_template) {
			Log::warning('Email template not found for alias: ' . $alias . ' - email_templates table has been deleted');
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
			Log::error('Email sending failed: ' . $e->getMessage());
			return false;
		}

	}

	protected function send_multipleattachment_email_template($replace = array(), $replace_with = array(), $alias = null, $to = null, $subject = null, $sender = null,$invoicearray)
	{
		// email_templates table has been deleted - using fallback content
		$email_template	= 	DB::table('email_templates')->where('alias', $alias)->first();
		if(!$email_template) {
			Log::warning('Email template not found for alias: ' . $alias . ' - email_templates table has been deleted');
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
			Mail::mailer('sendgrid')->to($explodeTo)->queue(new MultipleattachmentEmailManager($invoicearray));
			
			return true;
		} catch (\Exception $e) {
			Log::error('Email sending failed: ' . $e->getMessage());
			return false;
		}

	}

	protected function send_multiple_attach_compose($to = null, $subject = null,$sender,$invoicearray)
	{
		try {
			$explodeTo = explode(';', $to);//for multiple and single to
			$invoicearray['from'] = $sender;
			Mail::mailer('sendgrid')->to($explodeTo)->queue(new MultipleattachmentEmailManager($invoicearray));
			
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
			return;
		}

		// CRM access approvers get the same unrestricted admin console access as Super Admin
		$actor = Auth::user();
		if ($actor instanceof \App\Models\Staff && app(\App\Services\CrmAccess\CrmAccessService::class)->hasAdminConsoleLikeSuperAdminAccess($actor)) {
			return;
		}

		$userrole = UserRole::where('usertype', $role)->first();
		if ($userrole) {
			$module_access = $userrole->module_access;

			$noAccessController = json_decode($module_access);

			if (!in_array($controller, $noAccessController)) {
				return true;
			}
		}
	}

	public function curlRequest($url,$type="PUT",$data){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);
        // enable header only for POST;
        if($type=='POST'){
            curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_POST, 1);
			 curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			// curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);
        }else{
            curl_setopt($curl, CURLOPT_HEADER, FALSE);
        }

       curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);

 	curl_close($curl);

        return $response;
    }

	public function createSlug($userid, $table, $title, $id = 0)
    {
        // Normalize the title
        $slug = Str::slug($title);

        // Get any that could possibly be related.
        // This cuts the queries down by doing it once.
        $allSlugs = $this->getRelatedSlugs($userid, $table, $slug, $id);

        // If we haven't used it before then we are all good.
        if (! $allSlugs->contains('slug', $slug)){
            return $slug;
        }

        // Just append numbers like a savage until we find not used.
        for ($i = 1; $i <= 10; $i++) {
            $newSlug = $slug.'-'.$i;
            if (! $allSlugs->contains('slug', $newSlug)) {
                return $newSlug;
            }
        }

        throw new \Exception('Can not create a unique slug');
    }

    public function getRelatedSlugs($userid, $table, $slug, $id = 0)
    {
        return DB::table($table)->where('slug', 'like', $slug.'%')
            ->where('id', '<>', $id)
            ->where('user_id', '=', $userid)
            ->get();
    }

	public function createlocSlug($table, $title, $id = 0)
    {
        // Normalize the title
        $slug = Str::slug($title);

        // Get any that could possibly be related.
        // This cuts the queries down by doing it once.
        $allSlugs = $this->getlocRelatedSlugs($table, $slug, $id);

        // If we haven't used it before then we are all good.
        if (! $allSlugs->contains('slug', $slug)){
            return $slug;
        }

        // Just append numbers like a savage until we find not used.
        for ($i = 1; $i <= 10; $i++) {
            $newSlug = $slug.'-'.$i;
            if (! $allSlugs->contains('slug', $newSlug)) {
                return $newSlug;
            }
        }

        throw new \Exception('Can not create a unique slug');
    }

    public function getlocRelatedSlugs($table, $slug, $id = 0)
    {
        return DB::table($table)->where('slug', 'like', $slug.'%')
            ->where('id', '<>', $id)

            ->get();
    }

		public static function convert_number_to_words($number) {

		$hyphen      = '-';
		$conjunction = '  ';
		$separator   = ' ';
		$negative    = 'negative ';
		$decimal     = ' point ';
		$dictionary  = array(
			0                   => 'Zero',
			1                   => 'One',
			2                   => 'Two',
			3                   => 'Three',
			4                   => 'Four',
			5                   => 'Five',
			6                   => 'Six',
			7                   => 'Seven',
			8                   => 'Eight',
			9                   => 'Nine',
			10                  => 'Ten',
			11                  => 'Eleven',
			12                  => 'Twelve',
			13                  => 'Thirteen',
			14                  => 'Fourteen',
			15                  => 'Fifteen',
			16                  => 'Sixteen',
			17                  => 'Seventeen',
			18                  => 'Eighteen',
			19                  => 'Nineteen',
			20                  => 'Twenty',
			30                  => 'Thirty',
			40                  => 'Fourty',
			50                  => 'Fifty',
			60                  => 'Sixty',
			70                  => 'Seventy',
			80                  => 'Eighty',
			90                  => 'Ninety',
			100                 => 'Hundred',
			1000                => 'Thousand',
			1000000             => 'Million',
			1000000000          => 'Billion',
			1000000000000       => 'Trillion',
			1000000000000000    => 'Quadrillion',
			1000000000000000000 => 'Quintillion'
		);

		if (!is_numeric($number)) {
			return false;
		}

		if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
			// overflow
			trigger_error(
				'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
				E_USER_WARNING
			);
			return false;
		}

		if ($number < 0) {
			return $negative . self::convert_number_to_words(abs($number));
		}

		$string = $fraction = null;

		if (strpos($number, '.') !== false) {
			list($number, $fraction) = explode('.', $number);
		}

		switch (true) {
			case $number < 21:
				$string = $dictionary[$number];
				break;
			case $number < 100:
				$tens   = ((int) ($number / 10)) * 10;
				$units  = $number % 10;
				$string = $dictionary[$tens];
				if ($units) {
					$string .= $hyphen . $dictionary[$units];
				}
				break;
			case $number < 1000:
				$hundreds  = $number / 100;
				$remainder = $number % 100;
				$string = $dictionary[$hundreds] . ' ' . $dictionary[100];
				if ($remainder) {
					$string .= $conjunction . self::convert_number_to_words($remainder);
				}
				break;
			default:
				$baseUnit = pow(1000, floor(log($number, 1000)));
				$numBaseUnits = (int) ($number / $baseUnit);
				$remainder = $number % $baseUnit;
				$string = self::convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
				if ($remainder) {
					$string .= $remainder < 100 ? $conjunction : $separator;
					$string .= self::convert_number_to_words($remainder);
				}
				break;
		}

		if (null !== $fraction && is_numeric($fraction)) {
			$string .= $decimal;
			$words = array();
			foreach (str_split((string) $fraction) as $number) {
				$words[] = $dictionary[$number];
			}
			$string .= implode(' ', $words);
		}

		return $string;
	}


	public function createEmailSlug($table, $title, $id = 0)
    {
        // Normalize the title
        $slug = Str::slug($title);

        // Get any that could possibly be related.
        // This cuts the queries down by doing it once.
        $allSlugs = $this->getRelatedEmailSlugs($table, $slug, $id);

        // If we haven't used it before then we are all good.
        if (! $allSlugs->contains('alias', $slug)){
            return $slug;
        }

        // Just append numbers like a savage until we find not used.
        for ($i = 1; $i <= 10; $i++) {
            $newSlug = $slug.'-'.$i;
            if (! $allSlugs->contains('alias', $newSlug)) {
                return $newSlug;
            }
        }

        throw new \Exception('Can not create a unique slug');
    }

    public function getRelatedEmailSlugs($table, $slug, $id = 0)
    {
        return DB::table($table)->where('alias', 'like', $slug.'%')
            ->where('id', '<>', $id)
            ->get();
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

public function create_captcha($data = '', $img_path = '', $img_url = '', $font_path = '') {

	$defaults = array(
		'word'		=> '',
		'img_path'	=> '',
		'img_url'	=> '',
		'img_width'	=> '250',
		'img_height'	=> '55',
		//'font_path'	=> '',
		'font_path'	=> public_path().'/fonts/monofont.ttf',
		'expiration'	=> 7200,
		'word_length'	=> 6,
		'font_size'	=> 40,
		'img_id'	=> '',
		//'pool'		=> '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
		'pool'		=> '1234567890',
		'colors'	=> array(
			// 'background'	=> array(255,255,255),
			// 'border'	=> array(153,102,102),
			// 'text'		=> array(204,153,153),
			// 'grid'		=> array(255,182,182)


			'background'	=> array(255,255,255),
			'border'	=> array(255,255,255),
			'text'		=> array(255,39,0),
			'grid'		=> array(255,255,255)
		)
	);

	foreach ($defaults as $key => $val)
	{
		if ( ! is_array($data) && empty($$key))
		{
			$$key = $val;
		}
		else
		{
			$$key = isset($data[$key]) ? $data[$key] : $val;
		}
	}

	if ($img_path === '' OR $img_url === ''
		OR ! is_dir($img_path) OR ! $this->is_really_writable($img_path)
		OR ! extension_loaded('gd'))
	{
		return FALSE;
	}

	// -----------------------------------
	// Remove old images
	// -----------------------------------

	$now = microtime(TRUE);

	$current_dir = @opendir($img_path);
	while ($filename = @readdir($current_dir))
	{
		if (in_array(substr($filename, -4), array('.jpg', '.png'))
			&& (str_replace(array('.jpg', '.png'), '', $filename) + $expiration) < $now)
		{
			@unlink($img_path.$filename);
		}
	}

	@closedir($current_dir);

	// -----------------------------------
	// Do we have a "word" yet?
	// -----------------------------------

	if (empty($word))
	{
		$word = '';
		$pool_length = strlen($pool);
		$rand_max = $pool_length - 1;

		// PHP7 or a suitable polyfill
		if (function_exists('random_int'))
		{
			try
			{
				for ($i = 0; $i < $word_length; $i++)
				{
					$word .= $pool[random_int(0, $rand_max)];
				}
			}
			catch (\Exception $e)
			{
				// This means fallback to the next possible
				// alternative to random_int()
				$word = '';
			}
		}
	}

	if (empty($word))
	{
		// Nobody will have a larger character pool than
		// 256 characters, but let's handle it just in case ...
		//
		// No, I do not care that the fallback to mt_rand() can
		// handle it; if you trigger this, you're very obviously
		// trying to break it. -- Narf
		if ($pool_length > 256)
		{
			return FALSE;
		}

		// We'll try using the operating system's PRNG first,
		// which we can access through CI_Security::get_random_bytes()
		$ciInstance = function_exists('get_instance') ? call_user_func('get_instance') : null;
		$security = is_object($ciInstance) && isset($ciInstance->security) ? $ciInstance->security : null;
		if (! $security || ! method_exists($security, 'get_random_bytes'))
		{
			$word = '';
		}

		// To avoid numerous get_random_bytes() calls, we'll
		// just try fetching as much bytes as we need at once.
		if ($word === '' && ($bytes = $security->get_random_bytes($pool_length)) !== FALSE)
		{
			$byte_index = $word_index = 0;
			while ($word_index < $word_length)
			{
				// Do we have more random data to use?
				// It could be exhausted by previous iterations
				// ignoring bytes higher than $rand_max.
				if ($byte_index === $pool_length)
				{
					// No failures should be possible if the
					// first get_random_bytes() call didn't
					// return FALSE, but still ...
					for ($i = 0; $i < 5; $i++)
					{
						if (($bytes = $security->get_random_bytes($pool_length)) === FALSE)
						{
							continue;
						}

						$byte_index = 0;
						break;
					}

					if ($bytes === FALSE)
					{
						// Sadly, this means fallback to mt_rand()
						$word = '';
						break;
					}
				}

				list(, $rand_index) = unpack('C', $bytes[$byte_index++]);
				if ($rand_index > $rand_max)
				{
					continue;
				}

				$word .= $pool[$rand_index];
				$word_index++;
			}
		}
	}

	if (empty($word))
	{
		for ($i = 0; $i < $word_length; $i++)
		{
			$word .= $pool[mt_rand(0, $rand_max)];
		}
	}
	elseif ( ! is_string($word))
	{
		$word = (string) $word;
	}

	// -----------------------------------
	// Determine angle and position
	// -----------------------------------
	$length	= strlen($word);
	$angle	= ($length >= 6) ? mt_rand(-($length-6), ($length-6)) : 0;
	//$angle	= 360;
	$x_axis	= mt_rand(6, (360/$length)-16);
	//$x_axis	= mt_rand(6, (180));
	$y_axis = ($angle >= 0) ? mt_rand($img_height, $img_width) : mt_rand(6, $img_height);

	// Create image
	// PHP.net recommends imagecreatetruecolor(), but it isn't always available
	$im = function_exists('imagecreatetruecolor')
		? imagecreatetruecolor($img_width, $img_height)
		: imagecreate($img_width, $img_height);

	// -----------------------------------
	//  Assign colors
	// ----------------------------------

	is_array($colors) OR $colors = $defaults['colors'];
	$allocatedColors = array();

	foreach (array_keys($defaults['colors']) as $key)
	{
		// Check for a possible missing value
		$rgb = (isset($colors[$key]) && is_array($colors[$key])) ? $colors[$key] : $defaults['colors'][$key];
		$allocatedColors[$key] = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
	}

	// Create the rectangle
	ImageFilledRectangle($im, 0, 0, $img_width, $img_height, $allocatedColors['background']);

	// -----------------------------------
	//  Create the spiral pattern
	// -----------------------------------
	/* 	$theta		= 1;
		$thetac		= 7;
		$radius		= 16;
		$circles	= 20;
		$points		= 32;





	 for ($i = 0, $cp = ($circles * $points) - 1; $i < $cp; $i++)
	{
		$theta += $thetac;
		$rad = $radius * ($i / $points);
		$x = ($rad * cos($theta)) + $x_axis;
		$y = ($rad * sin($theta)) + $y_axis;
		$theta += $thetac;
		$rad1 = $radius * (($i + 1) / $points);
		$x1 = ($rad1 * cos($theta)) + $x_axis;
		$y1 = ($rad1 * sin($theta)) + $y_axis;
		imageline($im, $x, $y, $x1, $y1, $colors['grid']);
		$theta -= $thetac;
	}  */

	// -----------------------------------
	//  Write the text
	// -----------------------------------

	$use_font = ($font_path !== '' && file_exists($font_path) && function_exists('imagettftext'));
	if ($use_font === FALSE)
	{
		($font_size > 5) && $font_size = 5;
		//$x = mt_rand(0, $img_width / ($length / 3));
		$x = mt_rand(0, $img_width / ($length ));
		$y = 0;
	}
	else
	{
		($font_size > 30) && $font_size = 30;
		//$x = mt_rand(0, $img_width / ($length / 1.5));
		$x = mt_rand(0, $img_width / ($length ));
		$y = $font_size + 2;
	}

	for ($i = 0; $i < $length; $i++)
	{
		if ($use_font === FALSE)
		{
			$y = mt_rand(0 , $img_height / 2);
			imagestring($im, $font_size, $x, $y, $word[$i], $allocatedColors['text']);
			$x += ($font_size * 2);
		}
		else
		{
			$y = mt_rand($img_height / 2, $img_height - 3);
			imagettftext($im, $font_size, $angle, $x, $y, $allocatedColors['text'], $font_path, $word[$i]);
			$x += $font_size;
		}
	}

	// Create the border
	imagerectangle($im, 0, 0, $img_width - 1, $img_height - 1, $allocatedColors['border']);

	// -----------------------------------
	//  Generate the image
	// -----------------------------------
	$img_url = rtrim($img_url, '/').'/';

	if (function_exists('imagejpeg'))
	{
		$img_filename = $now.'.jpg';
		imagejpeg($im, $img_path.$img_filename);
	}
	elseif (function_exists('imagepng'))
	{
		$img_filename = $now.'.png';
		imagepng($im, $img_path.$img_filename);
	}
	else
	{
		return FALSE;
	}

	$img = '<img '.($img_id === '' ? '' : 'id="'.$img_id.'"').' src="'.$img_url.$img_filename.'" style="width: '.$img_width.'; height: '.$img_height .'; border: 0;" alt="captcha" />';
	ImageDestroy($im);

	return array('word' => $word, 'time' => $now, 'image' => $img, 'filename' => $img_filename);
}

public static function is_really_writable($file)
{
	// If we're on a Unix server with safe_mode off we call is_writable
	if (DIRECTORY_SEPARATOR === '/' && (Self::is_php('5.4') OR ! ini_get('safe_mode')))
	{
		return is_writable($file);
	}

	/* For Windows servers and safe_mode "on" installations we'll actually
	 * write a file then read it. Bah...
	 */
	if (is_dir($file))
	{
		$file = rtrim($file, '/').'/'.md5(mt_rand());
		if (($fp = @fopen($file, 'ab')) === FALSE)
		{
			return FALSE;
		}

		fclose($fp);
		@chmod($file, 0777);
		@unlink($file);
		return TRUE;
	}
	elseif ( ! is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE)
	{
		return FALSE;
	}

	fclose($fp);
	return TRUE;
}

public static function is_php($version)
{
	static $_is_php;
	$version = (string) $version;

	if ( ! isset($_is_php[$version]))
	{
		$_is_php[$version] = version_compare(PHP_VERSION, $version, '>=');
	}

	return $_is_php[$version];
}
}
