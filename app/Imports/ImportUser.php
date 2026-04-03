<?php
namespace App\Imports;
use App\Models\AgentDetails;
use Maatwebsite\Excel\Concerns\ToModel;
class ImportUser implements ToModel
{
   public function model(array $row, $request)
   {
	   if($request->struture == 'Individual'){
		   $full_name = $row[0];
		   $business_name = '';
		   $tax_number = '';
		   $contract_expiry_date = '';
		   $country_code = $row[2];
		   $phone = $row[3];
		   $email = $row[1];
		   $address = $row[4];
		   $city = $row[5];
		   $state = $row[6];
		   $zip = $row[7];
		   $country = $row[8];
		   $income_sharing = $row[10];
		   $claim_revenue = $row[9];
	   }else{
		   $full_name = $row[1];
		   $business_name = $row[0];
		   $tax_number = $row[2];
		   $contract_expiry_date = $row[3];
		   $country_code = $row[5];
		   $phone = $row[6];
		   $email = $row[4];
		   $address = $row[7];
		   $city = $row[8];
		   $state = $row[9];
		   $zip = $row[10];
		   $country = $row[11];
		   $income_sharing = $row[12];
		   $claim_revenue = $row[13];
	   }
       return new AgentDetails([
           'full_name' => $full_name,
           'agent_type' => implode(',',@$request->super_agent),
           'related_office' => $request->related_office,
           'struture' => $request->struture,
           'business_name' => $business_name,
           'tax_number' => $tax_number,
           'contract_expiry_date' => $contract_expiry_date,
           'country_code' => $country_code,
           'phone' => $phone,
           'email' => $email,
           'address' => $address,
           'city' => $city,
           'state' => $state,
           'zip' => $zip,
           'country' => $country,
           'income_sharing' => $income_sharing,
           'claim_revenue' => $claim_revenue,
       ]);
   }
}