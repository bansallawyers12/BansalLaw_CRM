<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class ClientPortalCommonListingController extends BaseController
{
    /**
     * Get list of all countries
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCountries(Request $request)
    {
        try {
            // Get optional query parameters
            $status = $request->query('status', null);

            // Build query
            $query = Country::query();

            // Filter by status if provided
            if ($status !== null) {
                $query->where('status', $status);
            }

            // Get all countries
            $countries = $query->get();

            // Separate India and Australia from the rest
            $india = null;
            $australia = null;
            $otherCountries = [];

            foreach ($countries as $country) {
                if ($country->name === 'India') {
                    $india = $country;
                } elseif ($country->name === 'Australia') {
                    $australia = $country;
                } else {
                    $otherCountries[] = $country;
                }
            }

            // Sort other countries alphabetically by name
            usort($otherCountries, function ($a, $b) {
                return strcmp($a->name, $b->name);
            });

            // Combine: India first, then Australia, then others
            $sortedCountries = [];
            if ($india) {
                $sortedCountries[] = $india;
            }
            if ($australia) {
                $sortedCountries[] = $australia;
            }
            $sortedCountries = array_merge($sortedCountries, $otherCountries);

            // Format response - only name
            $result = array_map(function ($country) {
                return [
                    'name' => $country->name,
                ];
            }, $sortedCountries);

            return $this->sendResponse($result, 'Countries retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('An error occurred: ' . $e->getMessage(), [], 500);
        }
    }
}

