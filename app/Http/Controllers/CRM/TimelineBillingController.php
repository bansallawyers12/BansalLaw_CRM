<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Support\TimelineBillingSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TimelineBillingController extends Controller
{
    public function rates(): JsonResponse
    {
        if (! $this->actorCanUseTimelineBilling()) {
            return response()->json(['status' => false, 'message' => config('constants.unauthorized')], 403);
        }

        return response()->json([
            'status' => true,
            'gst_rate' => (float) config('crm.timeline_billing.gst_rate', 0.10),
            'structure' => TimelineBillingSchedule::structure(),
            'rates' => TimelineBillingSchedule::all(),
        ]);
    }

    public function saveRates(Request $request): JsonResponse
    {
        if (! $this->actorCanUseTimelineBilling()) {
            return response()->json(['status' => false, 'message' => config('constants.unauthorized')], 403);
        }

        $ratesInput = $request->input('rates', $request->input('amounts', []));
        if (! is_array($ratesInput)) {
            return response()->json(['status' => false, 'message' => 'Invalid rates payload.'], 422);
        }

        $normalized = [];
        foreach ($ratesInput as $key => $value) {
            if (is_array($value) && array_key_exists('amount_incl_gst', $value)) {
                $normalized[(string) $key] = $value['amount_incl_gst'];
            } else {
                $normalized[(string) $key] = $value;
            }
        }

        try {
            $saved = TimelineBillingSchedule::saveAmounts($normalized);
        } catch (\Throwable $e) {
            Log::error('Failed to save timeline billing rates: '.$e->getMessage());

            return response()->json(['status' => false, 'message' => 'Could not save billing rates.'], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Billing structure amounts saved.',
            'structure' => TimelineBillingSchedule::structure(),
            'rates' => $saved,
        ]);
    }

    private function actorCanUseTimelineBilling(): bool
    {
        $actor = Auth::user();

        return $actor instanceof Staff && $actor->canUseTimelineBilling();
    }
}
