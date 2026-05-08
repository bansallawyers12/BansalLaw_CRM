<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ClientLegalForm;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\Note;
use App\Services\LegalFormDocxService;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LegalFormsController extends Controller
{
    private LegalFormDocxService $docxService;

    public function __construct(LegalFormDocxService $docxService)
    {
        $this->middleware('auth:admin');
        $this->docxService = $docxService;
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

        // Ensure numeric fields are never null
        $numericFields = [
            'estimated_legal_fees', 'estimated_disbursements', 'estimated_barrister_fees',
            'gst_amount', 'estimated_total', 'fixed_fee_amount', 'retainer_amount',
        ];
        foreach ($numericFields as $field) {
            $data[$field] = floatval($data[$field] ?? 0);
        }

        if (in_array($data['form_type'], ['short_costs_disclosure', 'cost_agreement'])) {
            $fees = $data['estimated_legal_fees'];
            $disbursements = $data['estimated_disbursements'];
            $barrister = $data['estimated_barrister_fees'];
            $data['gst_amount'] = round($fees * 0.10, 2);
            $data['estimated_total'] = $fees + $disbursements + $barrister + $data['gst_amount'];
        }

        try {
            $form = DB::transaction(function () use ($data) {
                $form = ClientLegalForm::create($data);
                $docxPath = $this->docxService->generate($form);
                $form->update(['pdf_path' => $docxPath]);

                return $form;
            });
        } catch (\Throwable $e) {
            Log::error('Legal form create failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Could not generate the Word document. Check storage permissions and templates.',
            ], 422);
        }

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

        $numericFields = [
            'estimated_legal_fees', 'estimated_disbursements', 'estimated_barrister_fees',
            'gst_amount', 'estimated_total', 'fixed_fee_amount', 'retainer_amount',
        ];
        foreach ($numericFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = floatval($data[$field] ?? 0);
            }
        }

        if (in_array($legalForm->form_type, ['short_costs_disclosure', 'cost_agreement'])) {
            $fees = floatval($data['estimated_legal_fees'] ?? $legalForm->estimated_legal_fees);
            $disbursements = floatval($data['estimated_disbursements'] ?? $legalForm->estimated_disbursements);
            $barrister = floatval($data['estimated_barrister_fees'] ?? $legalForm->estimated_barrister_fees);
            $data['gst_amount'] = round($fees * 0.10, 2);
            $data['estimated_total'] = $fees + $disbursements + $barrister + $data['gst_amount'];
        }

        try {
            DB::transaction(function () use ($legalForm, $data) {
                $legalForm->update($data);
                $docxPath = $this->docxService->generate($legalForm);
                $legalForm->update(['pdf_path' => $docxPath]);
            });
        } catch (\Throwable $e) {
            Log::error('Legal form update failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Could not regenerate the Word document.',
            ], 422);
        }

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

    public function downloadDocx(ClientLegalForm $legalForm)
    {
        $docxPath = $this->docxService->generate($legalForm);
        $legalForm->update(['pdf_path' => $docxPath]);

        $fullPath = public_path($docxPath);
        if (!file_exists($fullPath)) {
            abort(404, 'Document not found.');
        }

        $client = $legalForm->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $typeLabel = str_replace(' ', '_', ClientLegalForm::FORM_TYPES[$legalForm->form_type] ?? 'Form');
        $filename = $clientName . '_' . $typeLabel . '.docx';

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function previewDocx(ClientLegalForm $legalForm)
    {
        $docxPath = $this->docxService->generate($legalForm);
        $legalForm->update(['pdf_path' => $docxPath]);

        $fullPath = public_path($docxPath);
        if (!file_exists($fullPath)) {
            abort(404, 'Document not found.');
        }

        return response()->download($fullPath, null, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'inline; filename="' . basename($docxPath) . '"',
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

    public function generateScopeAI(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:admins,id',
            'client_matter_id' => 'nullable|exists:client_matters,id',
            'matter_reference' => 'nullable|string|max:100',
            'form_type' => 'required|in:short_costs_disclosure,cost_agreement,authority_to_act',
            'field' => 'required|in:scope_of_work,authority_scope,variables_affecting_costs',
        ]);

        $client = Admin::find($request->client_id);
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));

        $clientMatterId = $request->client_matter_id;
        if (! $clientMatterId && $request->filled('matter_reference')) {
            $ref = trim((string) $request->matter_reference);
            $resolved = ClientMatter::where('client_id', (int) $request->client_id)
                ->where('client_unique_matter_no', $ref)
                ->value('id');
            if ($resolved) {
                $clientMatterId = $resolved;
            }
        }

        $contextParts = [];
        $contextParts[] = "Client: {$clientName}";
        if ($client->address) {
            $contextParts[] = "Address: {$client->address}, {$client->city}, {$client->state} {$client->zip}";
        }
        if ($client->email) {
            $contextParts[] = "Email: {$client->email}";
        }
        if ($client->phone) {
            $contextParts[] = "Phone: {$client->phone}";
        }

        $documents = collect();
        if ($clientMatterId) {
            $matter = ClientMatter::with(['matter', 'personResponsible', 'legalPractitioner'])
                ->where('client_id', (int) $request->client_id)
                ->find($clientMatterId);

            if ($matter) {
                $matterType = $matter->matter ? $matter->matter->title : '';
                $matterNick = $matter->matter ? $matter->matter->nick_name : '';
                $caseDetail = $matter->case_detail ?? '';

                if ($matterType) {
                    $contextParts[] = "Matter Type: {$matterType}";
                }
                if ($matterNick) {
                    $contextParts[] = "Matter Category: {$matterNick}";
                }
                if ($caseDetail) {
                    $contextParts[] = "Case Details: {$caseDetail}";
                }
                if ($matter->client_unique_matter_no) {
                    $contextParts[] = "Matter Reference: {$matter->client_unique_matter_no}";
                }
                if ($matter->personResponsible) {
                    $contextParts[] = 'Person Responsible: '.trim($matter->personResponsible->first_name.' '.$matter->personResponsible->last_name);
                }
                if ($matter->date_of_incidence) {
                    $contextParts[] = 'Date of Incident: '.$matter->date_of_incidence->format('d/m/Y');
                }
                if ($matter->incidence_type) {
                    $contextParts[] = "Incident Type: {$matter->incidence_type}";
                }

                $documents = Document::where('client_matter_id', $matter->id)
                    ->whereNotNull('file_name')
                    ->select('file_name', 'doc_type', 'folder_name')
                    ->limit(30)
                    ->get();

                if ($documents->isNotEmpty()) {
                    $docList = $documents->map(function ($doc) {
                        $parts = [$doc->file_name];
                        if ($doc->doc_type) {
                            $parts[] = "({$doc->doc_type})";
                        }
                        if ($doc->folder_name) {
                            $parts[] = "[{$doc->folder_name}]";
                        }

                        return implode(' ', $parts);
                    })->implode('; ');
                    $contextParts[] = "Documents uploaded: {$docList}";
                }
            }
        }

        $notesBlock = $this->buildMatterNotesContextForAi((int) $request->client_id, $clientMatterId ? (int) $clientMatterId : null);

        $contextString = implode("\n", $contextParts);

       
        $formTypeLabel = ClientLegalForm::FORM_TYPES[$request->form_type] ?? $request->form_type;

        $systemPrompts = [
            'scope_of_work' => "You are a legal assistant at an Australian law firm (Bansal Lawyers). Based on the client and matter information and the CRM notes provided, generate a professional Scope of Work description for a {$formTypeLabel}. The scope should clearly outline what legal services will be provided. Use details from the notes where they are relevant. Write in a formal, numbered list format suitable for an Australian legal costs disclosure document. Do not include any greeting or sign-off. Only output the scope text.",
            'authority_scope' => "You are a legal assistant at an Australian law firm (Bansal Lawyers). Based on the client and matter information and the CRM notes provided, generate a professional Authority to Act scope description. This should clearly state what the client is authorising the firm to do on their behalf. Use details from the notes where they are relevant. Write in formal legal language suitable for an Australian Authority to Act document. Do not include any greeting or sign-off. Only output the authority scope text.",
            'variables_affecting_costs' => "You are a legal assistant at an Australian law firm (Bansal Lawyers). Based on the client and matter information and the CRM notes provided, list the key variables that might affect the total legal costs. Write as a concise bullet-point list of factors. Examples include: complexity of the matter, amount of correspondence required, whether the other party cooperates, court involvement, expert reports needed, etc. Tailor the list to this specific matter. Do not include any greeting or sign-off. Only output the variables list.",
        ];

        $systemPrompt = $systemPrompts[$request->field] ?? $systemPrompts['scope_of_work'];

        $userContent = "Generate the text based on this information:\n\n{$contextString}\n\n---\n{$notesBlock}";

        try {
            $anthropicKey = config('services.anthropic.api_key');
            if (! empty($anthropicKey)) {
                $generatedText = $this->generateWithAnthropic($systemPrompt, $userContent);
            } else {
                $generatedText = $this->generateWithOpenAi($systemPrompt, $userContent);
            }

            return response()->json([
                'success' => true,
                'text' => trim($generatedText),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI generation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aggregate note text for the given client matter (notes.matter_id = client_matters.id).
     */
    private function buildMatterNotesContextForAi(int $clientId, ?int $clientMatterId): string
    {
        if (! $clientMatterId) {
            return 'CRM notes: No matter could be resolved (select a matter on the client record or enter a Matter Reference that matches this client). Notes were not loaded.';
        }

        $notes = Note::query()
            ->where('client_id', $clientId)
            ->where('matter_id', $clientMatterId)
            ->orderByDesc('created_at')
            ->limit(150)
            ->get(['title', 'description', 'created_at', 'is_action']);

        if ($notes->isEmpty()) {
            return 'CRM notes: No notes are linked to this matter in the CRM (matter-scoped notes only).';
        }

        $lines = [];
        $maxChars = 120000;
        $used = 0;

        foreach ($notes as $note) {
            $date = $note->created_at ? $note->created_at->format('Y-m-d H:i') : '';
            $kind = ((int) $note->is_action === 1) ? 'Action' : 'Note';
            $title = trim((string) ($note->title ?? ''));
            $body = trim((string) ($note->description ?? ''));
            $chunk = "[{$date}] {$kind}".($title !== '' ? ": {$title}" : '')."\n{$body}\n";
            if ($used + strlen($chunk) > $maxChars) {
                $lines[] = "\n[Additional older notes omitted to fit model context limit.]";

                break;
            }
            $lines[] = $chunk;
            $used += strlen($chunk);
        }

        return "CRM notes for this matter:\n\n".implode("\n", $lines);
    }

    private function generateWithAnthropic(string $systemPrompt, string $userContent): string
    {
        $verify = config('services.anthropic.http_verify');
        $http = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout((int) config('services.anthropic.timeout', 90));

        if ($verify === 'false' && app()->environment(['local', 'development'])) {
            $http = $http->withoutVerifying();
        } elseif (is_string($verify) && $verify !== '' && $verify !== 'false') {
            $http = $http->withOptions(['verify' => $verify]);
        }

        $response = $http->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 1000,
           // 'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
        ]);

       
        if (! $response->successful()) {
           
            $err = $response->json('error.message') ?? $response->body();

            throw new \RuntimeException(is_string($err) ? $err : 'Anthropic request failed');
        }

        $data = $response->json();
      

        $blocks = $data['content'] ?? [];
        $text = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text .= $block['text'];
            }
        }

        return $text;
    }

    private function generateWithOpenAi(string $systemPrompt, string $userContent): string
    {
        $openAiKey = config('services.openai.api_key');
        if (empty($openAiKey)) {
            throw new \RuntimeException('No AI provider configured. Set ANTHROPIC_API_KEY or OPENAI_API_KEY in .env.');
        }

        $openAiClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer '.$openAiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => config('services.openai.timeout', 30),
        ]);

        $response = $openAiClient->post('chat/completions', [
            'json' => [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);

        return (string) ($result['choices'][0]['message']['content'] ?? '');
    }
}
