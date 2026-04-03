<?php
namespace App\Helpers; // Your helpers namespace 
use App\Company;
use Auth;
use Exception;
use Twilio\Rest\Client;

class Helper
{
    public static function sendSms($receiverNumber,$message){
        $receiverNumber = $receiverNumber ?? "+610422905860";
        $message = $message;
    
        try {
            
            $account_sid = getenv("TWILIO_SID");
            $auth_token = getenv("TWILIO_TOKEN");
            $twilio_number = getenv("TWILIO_FROM");
    
            $client = new Client($account_sid, $auth_token);
            $client->messages->create($receiverNumber, [
                'from' => $twilio_number, 
                'body' => $message]);
            $res="SMS sent successfully.";
            return json_encode($res);
    
        } catch (Exception $e) {
            return json_encode($e->getMessage());
            // dd("Error: ". $e->getMessage());
        }
    }
    public static function changeDateFormate($date,$date_format){
        return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format($date_format);    
    }
    public static function getUserCompany(): ?object
    {
        $companyId = Auth::user()->comp_id;
        return Company::find($companyId);
    }
}