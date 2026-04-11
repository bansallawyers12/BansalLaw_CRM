<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ClientLegalForm;
use App\Models\ClientMatter;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LegalFormsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:admins,id',
            'client_matter_id' => 'nullable|exists:client_matters,id',
            'form_type' => 'required|in:short_costs_disclosure,cost_agreement,authority_to_act',
            'form_date' => 'nullable|date',
            'matter_reference' => 'nullable|string|max:100',
            'firm_name' => 'nullable|string|max:255',
            'firm_contact' => 'nullable|string|max:255',
            'firm_address' => 'nullable|string',
            'firm_phone' => 'nullable|string|max:50',
            'firm_mobile' => 'nullable|string|max:50',
            'firm_email' => 'nullable|string|max:255',
            'firm_state' => 'nullable|string|max:50',
            'firm_postcode' => 'nullable|string|max:10',
            'person_responsible' => 'nullable|string|max:255',
            'person_responsible_email' => 'nullable|string|max:255',
            'scope_of_work' => 'nullable|string',
            'estimated_legal_fees' => 'nullable|numeric|min:0',
            'estimated_disbursements' => 'nullable|numeric|min:0',
            'estimated_barrister_fees' => 'nullable|numeric|min:0',
            'fee_type' => 'nullable|string|in:fixed,hourly',
            'fixed_fee_amount' => 'nullable|numeric|min:0',
            'cost_estimate_breakdown' => 'nullable|string',
            'variables_affecting_costs' => 'nullable|string',
            'retainer_amount' => 'nullable|numeric|min:0',
            'payment_reference' => 'nullable|string|max:100',
            'authority_scope' => 'nullable|string',
        ]);

        $data = $request->all();
        $data['created_by'] = Auth::id();

        // Calculate GST and total for cost forms
        if (in_array($data['form_type'], ['short_costs_disclosure', 'cost_agreement'])) {
            $fees = floatval($data['estimated_legal_fees'] ?? 0);
            $disbursements = floatval($data['estimated_disbursements'] ?? 0);
            $barrister = floatval($data['estimated_barrister_fees'] ?? 0);
            $data['gst_amount'] = round($fees * 0.10, 2);
            $data['estimated_total'] = $fees + $disbursements + $barrister + $data['gst_amount'];
        }

        $form = ClientLegalForm::create($data);

        // Generate PDF
        $pdfPath = $this->generatePdf($form);
        $form->update(['pdf_path' => $pdfPath]);

        return response()->json([
            'success' => true,
            'message' => ClientLegalForm::FORM_TYPES[$form->form_type] . ' created successfully.',
            'form' => $form->load(['client', 'matter', 'creator']),
        ]);
    }

    public function show(ClientLegalForm $legalForm): JsonResponse
    {
        return response()->json([
            'success' => true,
            'form' => $legalForm->load(['client', 'matter', 'creator']),
        ]);
    }

    public function update(Request $request, ClientLegalForm $legalForm): JsonResponse
    {
        $request->validate([
            'scope_of_work' => 'nullable|string',
            'estimated_legal_fees' => 'nullable|numeric|min:0',
            'estimated_disbursements' => 'nullable|numeric|min:0',
            'estimated_barrister_fees' => 'nullable|numeric|min:0',
            'fee_type' => 'nullable|string|in:fixed,hourly',
            'fixed_fee_amount' => 'nullable|numeric|min:0',
            'person_responsible' => 'nullable|string|max:255',
            'person_responsible_email' => 'nullable|string|max:255',
            'authority_scope' => 'nullable|string',
        ]);

        $data = $request->all();

        if (in_array($legalForm->form_type, ['short_costs_disclosure', 'cost_agreement'])) {
            $fees = floatval($data['estimated_legal_fees'] ?? $legalForm->estimated_legal_fees);
            $disbursements = floatval($data['estimated_disbursements'] ?? $legalForm->estimated_disbursements);
            $barrister = floatval($data['estimated_barrister_fees'] ?? $legalForm->estimated_barrister_fees);
            $data['gst_amount'] = round($fees * 0.10, 2);
            $data['estimated_total'] = $fees + $disbursements + $barrister + $data['gst_amount'];
        }

        $legalForm->update($data);

        // Re-generate PDF
        $pdfPath = $this->generatePdf($legalForm);
        $legalForm->update(['pdf_path' => $pdfPath]);

        return response()->json([
            'success' => true,
            'message' => 'Form updated successfully.',
            'form' => $legalForm->fresh()->load(['client', 'matter', 'creator']),
        ]);
    }

    public function destroy(ClientLegalForm $legalForm): JsonResponse
    {
        if ($legalForm->pdf_path && file_exists(public_path($legalForm->pdf_path))) {
            unlink(public_path($legalForm->pdf_path));
        }
        $legalForm->delete();

        return response()->json([
            'success' => true,
            'message' => 'Form deleted successfully.',
        ]);
    }

    public function downloadPdf(ClientLegalForm $legalForm)
    {
        // Always regenerate fresh PDF on download
        $pdfPath = $this->generatePdf($legalForm);
        $legalForm->update(['pdf_path' => $pdfPath]);

        $fullPath = public_path($pdfPath);
        if (!file_exists($fullPath)) {
            abort(404, 'PDF not found.');
        }

        $client = $legalForm->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $typeLabel = str_replace(' ', '_', ClientLegalForm::FORM_TYPES[$legalForm->form_type] ?? 'Form');
        $filename = $clientName . '_' . $typeLabel . '.pdf';

        return response()->download($fullPath, $filename);
    }

    public function previewPdf(ClientLegalForm $legalForm)
    {
        $pdfPath = $this->generatePdf($legalForm);
        $legalForm->update(['pdf_path' => $pdfPath]);

        $fullPath = public_path($pdfPath);
        if (!file_exists($fullPath)) {
            abort(404, 'PDF not found.');
        }

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function getClientForms(Request $request): JsonResponse
    {
        $clientId = $request->query('client_id');
        $matterId = $request->query('matter_id');

        $query = ClientLegalForm::where('client_id', $clientId)
            ->with(['matter', 'creator']);

        if ($matterId) {
            $query->where(function ($q) use ($matterId) {
                $q->where('client_matter_id', $matterId)
                  ->orWhereNull('client_matter_id');
            });
        }

        $forms = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'forms' => $forms,
        ]);
    }

    private function generatePdf(ClientLegalForm $form): string
    {
        $form->load(['client', 'matter']);

        $client = $form->client;
        $clientName = trim(($client->first_name ?? '') . '_' . ($client->last_name ?? ''));
        $clientName = preg_replace('/[^a-zA-Z0-9_]/', '', $clientName);

        $viewName = match ($form->form_type) {
            'short_costs_disclosure' => 'crm.legal-forms.pdf.short-costs-disclosure',
            'cost_agreement' => 'crm.legal-forms.pdf.cost-agreement',
            'authority_to_act' => 'crm.legal-forms.pdf.authority-to-act',
        };

        $pdf = PDF::loadView($viewName, ['form' => $form]);
        $pdf->setPaper('A4', 'portrait');

        $dir = 'legal_forms/' . $form->client_id;
        $fullDir = public_path($dir);
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        $filename = $form->form_type . '_' . $form->id . '.pdf';
        $relativePath = $dir . '/' . $filename;

        $pdf->save(public_path($relativePath));

        return $relativePath;
    }
}
