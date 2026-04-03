<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Matter;
use App\Models\AnzscoOccupation;
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

    /**
     * Get list of visa types
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVisaTypes(Request $request)
    {
        try {
            // Get visa types from Matter table
            $visaTypes = Matter::select('id', 'title', 'nick_name')
                ->where('title', 'NOT LIKE', '%skill assessment%')
                ->where('status', 1)
                ->orderBy('title', 'ASC')
                ->get();

            // Format response
            $result = $visaTypes->map(function ($visaType) {
                return [
                    'id' => $visaType->id,
                    'title' => $visaType->title,
                    'nick_name' => $visaType->nick_name,
                ];
            });

            return $this->sendResponse($result, 'Visa types retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('An error occurred: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Search occupations - returns list of occupations matching search query
     * 
     * User must provide at least 2 characters to search.
     * Searches by ANZSCO code, occupation title, normalized title, and alternate titles.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchOccupationDetail(Request $request)
    {
        try {
            $query = $request->input('q', '');
            $limit = min((int) $request->input('limit', 20), 50);

            // Validate: query must be at least 2 characters
            if (empty($query) || strlen(trim($query)) < 2) {
                return $this->sendError('Search query must be at least 2 characters', [], 422);
            }

            // Search occupations using the same logic as the website
            $occupations = AnzscoOccupation::active()
                ->search(trim($query))
                ->limit($limit)
                ->get(['id', 'anzsco_code', 'occupation_title', 'assessing_authority', 
                       'assessment_validity_years', 'is_on_mltssl', 'is_on_stsol', 
                       'is_on_rol', 'is_on_csol']);

            // Format response - same format as the website API
            $result = $occupations->map(function($occ) {
                return [
                    'id' => $occ->id,
                    'anzsco_code' => $occ->anzsco_code,
                    'occupation_title' => $occ->occupation_title,
                    'assessing_authority' => $occ->assessing_authority,
                    'assessment_validity_years' => $occ->assessment_validity_years,
                    'lists' => $occ->occupation_lists,
                    'lists_string' => $occ->occupation_lists_string,
                    'label' => $occ->occupation_title . ' (' . $occ->anzsco_code . ')',
                    'value' => $occ->occupation_title
                ];
            });

            return $this->sendResponse($result, 'Occupations retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('An error occurred: ' . $e->getMessage(), [], 500);
        }
    }
}

