<?php
namespace App\Http\Controllers\CRM\Leads;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Staff;
use App\Models\Lead;
use App\Support\StaffClientVisibility;
use App\Services\LeadFormDataService;

class LeadAssignmentController extends Controller
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
     * Get assignable staff for leads (used by lead ownership / Assigned-to UI).
     * Legacy POST /leads/assign and /bulk-assign were removed — use Assigned to on the lead/client detail card.
     */
    public function getAssignableStaff(Request $request)
    {
        $user = Auth::user();
        if (! ($user instanceof Staff) || (int) $user->status !== 1) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $leadId = $request->input('lead_id');
        if ($leadId !== null && $leadId !== '') {
            $lead = Lead::find($leadId);
            if (! $lead) {
                return response()->json(['error' => 'Lead not found'], 404);
            }
            if (! StaffClientVisibility::canAccessClientOrLead((int) $lead->id, $user)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        } else {
            if (! $user->hasEffectiveSuperAdminPrivileges()) {
                return response()->json(['error' => 'Lead ID is required to view assignable staff.'], 422);
            }
        }
        
        return response()->json(
            app(LeadFormDataService::class)->assignableStaff(true)->values()
        );
    }

    /**
     * Decode string helper method - overrides parent method
     */
    public function decodeString($string = NULL)
    {
        if (base64_encode(base64_decode($string, true)) === $string) {
            return convert_uudecode(base64_decode($string));
        }
        return $string;
    }
}
