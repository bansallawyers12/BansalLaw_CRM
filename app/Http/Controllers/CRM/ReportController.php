<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClientVisaCountry;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
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
     * Visa Expiry Report - Calendar View
     *
     * @return \Illuminate\Http\Response
     */
    public function visaexpires(Request $request)
    {
        // Query to get all visa expiries with client information
        $visaExpiries = ClientVisaCountry::with(['client', 'matter'])
            ->whereNotNull('visa_expiry_date')
            ->whereHas('client', function($q) {
                $q->whereIn('type', ['client', 'lead'])
                  ->where('is_archived', 0)
                  ->whereNull('is_deleted');
            })
            ->orderBy('visa_expiry_date', 'asc')
            ->get();

        // Format data for FullCalendar
        $sched_res = [];
        foreach($visaExpiries as $visaExpiry) {
            // Skip if visa expiry date is invalid or can't be parsed
            if(empty($visaExpiry->visa_expiry_date) || !strtotime($visaExpiry->visa_expiry_date)) {
                continue;
            }

            $client = $visaExpiry->client;
            if (!$client) {
                continue;
            }

            // Determine event color based on expiry status
            $expiryDate = Carbon::parse($visaExpiry->visa_expiry_date);
            $today = Carbon::now();
            $daysUntilExpiry = $today->diffInDays($expiryDate, false);

            $color = '#3788d8'; // Default blue
            if ($daysUntilExpiry < 0) {
                $color = '#dc3545'; // Red for expired
            } elseif ($daysUntilExpiry <= 7) {
                $color = '#ffc107'; // Yellow/Orange for expiring soon
            }

            // Create client detail URL
            $clientId = base64_encode(convert_uuencode($client->id));
            $url = url('/clients/detail/' . $clientId);

            $visaExpiryArray = [
                'id' => $visaExpiry->id,
                'client_id' => $client->id,
                'stitle' => htmlspecialchars($client->first_name . ' ' . $client->last_name, ENT_QUOTES, 'UTF-8'),
                'startdate' => $expiryDate->format('Y-m-d'),
                'end' => $expiryDate->format('Y-m-d'),
                'displayDate' => $expiryDate->format('F d, Y'),
                'url' => $url,
                'color' => $color,
                'visa_country' => $client->country_passport ?? 'N/A',
                'visa_type' => $visaExpiry->matter ? $visaExpiry->matter->title : ($visaExpiry->visa_type ?? 'N/A'),
                'days_until_expiry' => $daysUntilExpiry,
            ];

            $sched_res[$visaExpiry->id] = $visaExpiryArray;
        }

        return view('crm.reports.visaexpires', compact('sched_res'));
    }
}
