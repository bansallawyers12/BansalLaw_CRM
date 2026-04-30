<?php
namespace App\Http\Controllers\CRM\Leads;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Lead;
use App\Models\Matter;
use App\Models\Staff;
use App\Support\StaffClientVisibility;

class LeadConversionController extends Controller
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
     * Convert lead to client
     * Only super admin can perform bulk conversions
     */
    public function convertToClient(Request $request)
    {
        $actor = Auth::user();
        if (! ($actor instanceof Staff && $actor->hasEffectiveSuperAdminPrivileges())) {
            return redirect()->back()->with('error', 'Only super admin can perform bulk conversions');
        }
        
        $requestData = $request->all();
        
        // Get all leads (including archived) for conversion
        $enqdatas = Lead::withArchived()->paginate(500);
        
        $convertedCount = 0;
        foreach($enqdatas as $lead){
            try {
                $lead->convertToClient();
                $convertedCount++;
            } catch (\Exception $e) {
                // Skip failed conversions
            }
        }
        
        return redirect()->back()->with('success', "Converted {$convertedCount} leads successfully");
    }

    /**
     * Convert single lead to client with matter creation
     * Anyone can convert a single lead
     */
    public function convertSingleLead(Request $request)
    {
        $requestData = $request->all();
        
        $leadId = $this->decodeString($requestData['lead_id']);
        $lead = Lead::withArchived()->find($leadId);
        
        if(!$lead) {
            return redirect()->back()->with('error', 'Lead not found');
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $lead->id, Auth::user())) {
            return redirect()->back()->with('error', config('constants.unauthorized'));
        }

        // Allow conversion without a pre-existing matter row only when this request also creates a matter (matter_id + legal_practitioner).
        $willCreateMatterInSameRequest = $request->filled('matter_id') && $request->filled('legal_practitioner');
        if (
            ! ClientMatter::clientHasActiveAssignedMatter((int) $lead->id)
            && ! $willCreateMatterInSameRequest
        ) {
            $encoded = base64_encode(convert_uuencode((string) $lead->id));

            return redirect()->route('clients.edit', $encoded)
                ->with('matter_required_before_convert', true)
                ->with('error', 'Assign a matter type before converting this lead to a client. Use the edit page to add an active matter, then try again.');
        }

        // Start transaction
        DB::beginTransaction();
        
        try {
            // Convert lead to client using Lead model method
            $client = $lead->convertToClient();
            
            // Update user_id if provided
            if(isset($requestData['user_id'])) {
                $client->user_id = $requestData['user_id'];
                $client->save();
            }

            // Create matter if matter data is provided
            if(isset($requestData['matter_id']) && isset($requestData['legal_practitioner'])) {
                $matterIdSingle = (int) $requestData['matter_id'];
                if (! Matter::allowedForClientIsCompany($matterIdSingle, (bool) $client->is_company)) {
                    DB::rollback();
                    return redirect()->back()->with('error', 'This matter type is not valid for this client record.');
                }
                $matter = new ClientMatter();
                $matter->user_id = $requestData['user_id'] ?? Auth::user()->id;
                $matter->client_id = $client->id;
                $matter->sel_legal_practitioner = $requestData['legal_practitioner'];
                $matter->sel_person_responsible = $requestData['person_responsible'] ?? null;
                $matter->sel_person_assisting = $requestData['person_assisting'] ?? null;
                $matter->sel_matter_id = $requestData['matter_id'];

                // Generate unique matter number
                $client_matters_cnt_per_client = DB::table('client_matters')
                    ->select('id')
                    ->where('sel_matter_id', $requestData['matter_id'])
                    ->where('client_id', $client->id)
                    ->count();
                    
                $client_matters_current_no = $client_matters_cnt_per_client + 1;

                $matter->client_unique_matter_no = Matter::clientUniqueMatterNoPrefix((int) $requestData['matter_id']) . '_' . $client_matters_current_no;
                
                $matterType = Matter::find($requestData['matter_id']);
                $workflowId = $matterType && $matterType->workflow_id ? $matterType->workflow_id : \App\Models\Workflow::where('name', 'General')->value('id');
                $firstStageId = \App\Models\WorkflowStage::where('workflow_id', $workflowId)->orderByRaw('COALESCE(sort_order, id) ASC')->value('id')
                    ?? \App\Models\WorkflowStage::orderByRaw('COALESCE(sort_order, id) ASC')->value('id') ?? 1;
                $matter->workflow_id = $workflowId;
                $matter->workflow_stage_id = $firstStageId;
                $matter->matter_status = 1; // Active by default
                $matter->save();
            }

            DB::commit();
            
            return redirect()->back()->with('success', 'Lead converted to client successfully');
            
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to convert lead: ' . $e->getMessage());
        }
    }

    /**
     * Bulk convert leads to clients
     * Only super admin can perform bulk conversions
     */
    public function bulkConvertToClient(Request $request)
    {
        $actor = Auth::user();
        if (! ($actor instanceof Staff && $actor->hasEffectiveSuperAdminPrivileges())) {
            return redirect()->back()->with('error', 'Only super admin can perform bulk conversions');
        }
        
        $requestData = $request->all();
        
        if(!isset($requestData['lead_ids'])) {
            return redirect()->back()->with('error', 'No leads selected');
        }

        $leadIds = $requestData['lead_ids'];
        $convertedCount = 0;
        $errors = [];

        foreach($leadIds as $leadId) {
            try {
                $lead = Lead::withArchived()->find($leadId);
                if($lead) {
                    $lead->convertToClient();
                    $convertedCount++;
                }
            } catch (\Exception $e) {
                $errors[] = "Lead ID {$leadId}: " . $e->getMessage();
            }
        }

        $message = "Successfully converted {$convertedCount} leads to clients";
        if(!empty($errors)) {
            $message .= ". Errors: " . implode(', ', $errors);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Get conversion statistics
     * Only super admin can view conversion stats
     */
    public function getConversionStats()
    {
        $actor = Auth::user();
        if (! ($actor instanceof Staff && $actor->hasEffectiveSuperAdminPrivileges())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $totalLeads = Lead::count();
        $totalClients = Admin::where('type', 'client')->count();
        $convertedThisMonth = Admin::where('type', 'client')
            ->where('type', 'client')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        return [
            'total_leads' => $totalLeads,
            'total_clients' => $totalClients,
            'converted_this_month' => $convertedThisMonth,
            'conversion_rate' => $totalLeads > 0 ? round(($totalClients / ($totalLeads + $totalClients)) * 100, 2) : 0
        ];
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
