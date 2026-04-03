<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Visa Pricing Estimator API
 *
 * Routes require client portal authentication (auth:sanctum).
 *
 * Mirrors the Australian Department of Home Affairs Visa Pricing Estimator:
 * https://immi.homeaffairs.gov.au/visas/visa-pricing-estimator
 */
class VisaPricingEstimatorController extends BaseController
{
    /**
     * Visa List with Search
     * GET /api/visa-estimate/visa-list
     *
     * Query Parameters (same style as GET /api/notifications):
     * - q: Search query - case-insensitive substring match on visa label only (optional)
     * - page: Page number (optional, default: 1, integer min 1)
     * - limit: Items per page (optional, default: 20, integer min 1, max 100)
     */
    public function getVisaList(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'q' => 'nullable|string|max:500',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $search = trim((string) $request->get('q', ''));
        $page = (int) $request->get('page', 1);
        $limit = min(max((int) $request->get('limit', 20), 1), 100);
        $page = max($page, 1);

        $visas = config('visa_pricing_estimator.visas', []);

        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $visas = array_values(array_filter($visas, function ($visa) use ($searchLower) {
                $label = mb_strtolower((string) ($visa['label'] ?? ''));

                return str_contains($label, $searchLower);
            }));
        }

        $total = count($visas);
        $lastPage = $total > 0 ? (int) ceil($total / $limit) : 1;
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $limit;
        $pageItems = array_slice($visas, $offset, $limit);

        $items = array_map(function ($visa) {
            $label = (string) ($visa['label'] ?? '');
            $subclass = null;
            if (preg_match('/\(\s*subclass\s*([^)]+)\)/i', $label, $matches)) {
                $subclass = trim($matches[1]);
            }

            return [
                'id' => $visa['id'],
                'label' => $label,
                'subclass' => $subclass,
                'stream' => null,
            ];
        }, $pageItems);

        $countOnPage = count($pageItems);
        $result = [
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $countOnPage > 0 ? $offset + 1 : null,
                'to' => $countOnPage > 0 ? $offset + $countOnPage : null,
            ],
        ];

        return $this->sendResponse($result, 'Visa list retrieved successfully');
    }

    /**
     * Get Estimate Result
     * POST /api/visa-estimate/estimate
     *
     * Request Body:
     * - visa_id: Visa ID from visa-list API (required)
     * - lodging_date: Application date (YYYY-MM-DD or d/m/Y) (optional, for future date-based pricing)
     * - lodging_online: true/false (optional, default true)
     * - primary_in_australia: true/false (optional)
     * - primary_holds_table2_visa: true/false (optional)
     * - additional_applicants_18_plus: number (optional, default 0)
     * - additional_applicants_u18: number (optional, default 0)
     */
    public function getEstimate(Request $request)
    {
        $validated = $request->validate([
            'visa_id' => ['required', 'integer', 'min:1'],
            'lodging_date' => ['nullable', 'string', 'date'],
            'lodging_online' => ['nullable', 'boolean'],
            'primary_in_australia' => ['nullable', 'boolean'],
            'primary_holds_table2_visa' => ['nullable', 'boolean'],
            'additional_applicants_18_plus' => ['nullable', 'integer', 'min:0', 'max:20'],
            'additional_applicants_u18' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $visaId = (string) $validated['visa_id'];
        $additional18Plus = (int) ($validated['additional_applicants_18_plus'] ?? 0);
        $additionalU18 = (int) ($validated['additional_applicants_u18'] ?? 0);

        $visas = config('visa_pricing_estimator.visas', []);
        $visa = collect($visas)->firstWhere('id', $visaId);

        if (!$visa) {
            return $this->sendError('Visa not found. Use /api/visa-estimate/visa-list for valid visa IDs.', [], 404);
        }

        $default18Plus = config('visa_pricing_estimator.additional_applicant_charge_18_plus', 4685);
        $defaultU18 = config('visa_pricing_estimator.additional_applicant_charge_u18', 2345);

        $charge18Plus = $visa['additional_18_plus'] ?? $default18Plus;
        $chargeU18 = $visa['additional_u18'] ?? $defaultU18;

        $lineItems = [];
        $total = 0.0;

        // Primary visa charge
        $baseCharge = (float) ($visa['base_charge'] ?? 0);
        $lineItems[] = [
            'product' => $visa['label'],
            'quantity' => 1,
            'price' => round($baseCharge, 2),
        ];
        $total += $baseCharge;

        // Additional applicants 18+
        if ($additional18Plus > 0 && $charge18Plus !== null) {
            $amount = $charge18Plus * $additional18Plus;
            $lineItems[] = [
                'product' => 'Additional Applicant Charge 18+',
                'quantity' => $additional18Plus,
                'price' => round($amount, 2),
            ];
            $total += $amount;
        }

        // Additional applicants under 18
        if ($additionalU18 > 0 && $chargeU18 !== null) {
            $amount = $chargeU18 * $additionalU18;
            $lineItems[] = [
                'product' => 'Additional Applicant Charge U18',
                'quantity' => $additionalU18,
                'price' => round($amount, 2),
            ];
            $total += $amount;
        }

        // GST (visa charges are generally GST-free)
        $lineItems[] = [
            'product' => 'GST',
            'quantity' => 1,
            'price' => 0.00,
        ];

        $result = [
            'line_items' => $lineItems,
            'total' => round($total, 2),
            'currency' => 'AUD',
            'price_starts_from' => 'AUD ' . number_format($total, 2, '.', ','),
            'disclaimer' => 'This is an estimate only. The estimator might not include the second instalment payable for some visas. Verify current charges at https://immi.homeaffairs.gov.au/visas/getting-a-visa/fees-and-charges',
        ];

        return $this->sendResponse($result, 'Estimate calculated successfully');
    }
}
