<?php
namespace App\Http\Controllers\CRM\Leads;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use App\Models\Staff;
use App\Models\Lead;
use App\Support\StaffClientVisibility;

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
     * Assign lead to staff/agent (deprecated - assignee column removed)
     */
    public function assign(Request $request) 
    {
        return redirect()->back()->with('info', 'Lead assignment has been deprecated.');
    }

    /**
     * Get assignable staff for leads
     * Only lead owner can access
     */
    public function getAssignableStaff(Request $request)
    {
        // Check if requesting for a specific lead (ownership verification)
        $leadId = $request->input('lead_id');
        
        if ($leadId) {
            $lead = Lead::find($leadId);
            if (! $lead) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            if (! StaffClientVisibility::canAccessClientOrLead((int) $lead->id, Auth::user())) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }
        
        return Staff::select('id', 'first_name', 'last_name', 'email')
            ->where('status', 1)
            ->get();
    }

    /**
     * Bulk assign leads to staff
     * Only super admin can perform bulk assignments
     */
    public function bulkAssign(Request $request)
    {
        // Check if staff is super admin (role = 1)
        if (Auth::user()->role != 1) {
            return redirect()->back()->with('error', 'Only super admin can perform bulk assignments');
        }
        
        $requestData = $request->all();
        
        if(!isset($requestData['lead_ids']) || !isset($requestData['assign_to'])) {
            return redirect()->back()->with('error', 'Missing required data');
        }

        return redirect()->back()->with('info', 'Lead assignment has been deprecated.');
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
