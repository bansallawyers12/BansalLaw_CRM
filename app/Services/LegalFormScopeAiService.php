<?php

namespace App\Services;

use App\Jobs\GenerateLegalFormScopeAiJob;
use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientLegalForm;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\Note;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LegalFormScopeAiService
{
    private const CACHE_TTL_MINUTES = 30;

    /**
     * @param  array{client_id:int,client_matter_id:?int,matter_reference:?string,form_type:string,field:string}  $payload
     */
    public function start(array $payload, int $staffId): string
    {
        $jobId = (string) Str::uuid();

        $this->putStatus($jobId, [
            'job_id' => $jobId,
            'staff_id' => $staffId,
            'status' => 'queued',
            'message' => 'AI generation queued.',
            'text' => null,
            'created_at' => now()->toIso8601String(),
        ]);

        $this->dispatchJob($jobId, $payload, $staffId);

        return $jobId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStatus(string $jobId, int $staffId): ?array
    {
        $data = Cache::get($this->cacheKey($jobId));
        if (! is_array($data) || (int) ($data['staff_id'] ?? 0) !== $staffId) {
            return null;
        }

        return $data;
    }

    /**
     * @param  array{client_id:int,client_matter_id:?int,matter_reference:?string,form_type:string,field:string}  $payload
     */
    public function run(string $jobId, array $payload, int $staffId): void
    {
        $this->putStatus($jobId, [
            'status' => 'processing',
            'message' => 'Generating text…',
        ], $staffId);

        try {
            $text = $this->generate($payload);
            $this->putStatus($jobId, [
                'status' => 'completed',
                'message' => 'AI text generated successfully.',
                'text' => $text,
            ], $staffId);
        } catch (\Throwable $e) {
            Log::warning('Legal form AI scope generation failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
            $this->putStatus($jobId, [
                'status' => 'failed',
                'message' => 'AI generation failed: '.$e->getMessage(),
                'text' => null,
            ], $staffId);
        }
    }

    /**
     * @param  array{client_id:int,client_matter_id:?int,matter_reference:?string,form_type:string,field:string}  $payload
     */
    public function generate(array $payload): string
    {
        $client = Admin::findOrFail((int) $payload['client_id']);
        $clientName = trim(($client->first_name ?? '').' '.($client->last_name ?? ''));

        $clientMatterId = $payload['client_matter_id'] ?? null;
        if (! $clientMatterId && ! empty($payload['matter_reference'])) {
            $ref = trim((string) $payload['matter_reference']);
            $resolved = ClientMatter::where('client_id', (int) $payload['client_id'])
                ->where('client_unique_matter_no', $ref)
                ->value('id');
            if ($resolved) {
                $clientMatterId = (int) $resolved;
            }
        }

        $contextParts = [];
        $contextParts[] = "Client: {$clientName}";
        $resolvedAddr = ClientAddress::where('client_id', $client->id)->orderByDesc('id')->first();
        $resolvedAddrStr = $resolvedAddr
            ? collect([$resolvedAddr->address_line_1, $resolvedAddr->address_line_2, $resolvedAddr->suburb, $resolvedAddr->state, $resolvedAddr->zip])->filter()->implode(', ')
            : collect([$client->address, $client->city, $client->state, $client->zip])->filter()->implode(', ');
        if ($resolvedAddrStr) {
            $contextParts[] = "Address: {$resolvedAddrStr}";
        }
        if ($client->email) {
            $contextParts[] = "Email: {$client->email}";
        }
        if ($client->phone) {
            $contextParts[] = "Phone: {$client->phone}";
        }

        if ($clientMatterId) {
            $matter = ClientMatter::with(['matter', 'personResponsible', 'legalPractitioner'])
                ->where('client_id', (int) $payload['client_id'])
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

        $notesBlock = $this->buildMatterNotesContextForAi((int) $payload['client_id'], $clientMatterId ? (int) $clientMatterId : null);
        $contextString = implode("\n", $contextParts);
        $formTypeLabel = ClientLegalForm::FORM_TYPES[$payload['form_type']] ?? $payload['form_type'];

        $systemPrompts = [
            'scope_of_work' => "You are a legal assistant at an Australian law firm (Bansal Lawyers). Based on the client and matter information and the CRM notes provided, generate a professional Scope of Work description for a {$formTypeLabel}. The scope should clearly outline what legal services will be provided. Use details from the notes where they are relevant. Write in a formal, numbered list format suitable for an Australian legal costs disclosure document. Do not include any greeting or sign-off. Only output the scope text.",
            'authority_scope' => 'You are a legal assistant at an Australian law firm (Bansal Lawyers). Based on the client and matter information and the CRM notes provided, generate a professional Authority to Act scope description. This should clearly state what the client is authorising the firm to do on their behalf. Use details from the notes where they are relevant. Write in formal legal language suitable for an Australian Authority to Act document. Do not include any greeting or sign-off. Only output the authority scope text.',
            'variables_affecting_costs' => 'You are a legal assistant at an Australian law firm (Bansal Lawyers). Based on the client and matter information and the CRM notes provided, list the key variables that might affect the total legal costs. Write as a concise bullet-point list of factors. Examples include: complexity of the matter, amount of correspondence required, whether the other party cooperates, court involvement, expert reports needed, etc. Tailor the list to this specific matter. Do not include any greeting or sign-off. Only output the variables list.',
        ];

        $systemPrompt = $systemPrompts[$payload['field']] ?? $systemPrompts['scope_of_work'];
        $userContent = "Generate the text based on this information:\n\n{$contextString}\n\n---\n{$notesBlock}";

        $anthropicKey = config('services.anthropic.api_key');
        if (! empty($anthropicKey)) {
            return trim($this->generateWithAnthropic($systemPrompt, $userContent));
        }

        return trim($this->generateWithOpenAi($systemPrompt, $userContent));
    }

    /**
     * @param  array{client_id:int,client_matter_id:?int,matter_reference:?string,form_type:string,field:string}  $payload
     */
    private function dispatchJob(string $jobId, array $payload, int $staffId): void
    {
        $connection = (string) config('crm.legal_forms.ai_queue_connection', 'sync');
        $afterResponse = (bool) config('crm.legal_forms.ai_after_response', true);

        $pending = GenerateLegalFormScopeAiJob::dispatch($jobId, $payload, $staffId);
        $pending->onConnection($connection);

        if ($afterResponse && $connection === 'sync') {
            $pending->afterResponse();
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function putStatus(string $jobId, array $changes, ?int $staffId = null): void
    {
        $existing = Cache::get($this->cacheKey($jobId));
        $existing = is_array($existing) ? $existing : [];

        if ($staffId !== null && ! isset($existing['staff_id'])) {
            $changes['staff_id'] = $staffId;
        }

        Cache::put(
            $this->cacheKey($jobId),
            array_merge($existing, $changes, ['updated_at' => now()->toIso8601String()]),
            now()->addMinutes(self::CACHE_TTL_MINUTES)
        );
    }

    private function cacheKey(string $jobId): string
    {
        return 'legal_form_scope_ai:'.$jobId;
    }

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
            $kind = ((int) $note->is_action === 1) ? 'Task' : 'Note';
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
            'system' => $systemPrompt,
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
