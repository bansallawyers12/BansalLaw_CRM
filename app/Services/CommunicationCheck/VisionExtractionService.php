<?php

namespace App\Services\CommunicationCheck;

use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Vision-only extractor: returns structured JSON from a communication screenshot.
 * Does not query the CRM or decide accountability.
 */
class VisionExtractionService
{
    /**
     * @return array{
     *     channel: string,
     *     direction: string,
     *     from: ?string,
     *     to: ?string,
     *     phone: ?string,
     *     subject: ?string,
     *     snippet: ?string,
     *     datetime: ?string,
     *     datetime_raw: ?string,
     *     app: ?string,
     *     extract_confidence: int,
     *     notes: ?string
     * }
     */
    public function extractFromImage(UploadedFile|string $fileOrPath, ?string $mimeHint = null): array
    {
        $path = $fileOrPath instanceof UploadedFile ? $fileOrPath->getRealPath() : $fileOrPath;
        if (! $path || ! is_file($path)) {
            throw new \RuntimeException('Screenshot file not found.');
        }

        $mime = $mimeHint
            ?? ($fileOrPath instanceof UploadedFile ? $fileOrPath->getMimeType() : null)
            ?? (mime_content_type($path) ?: 'image/jpeg');

        if (! str_starts_with((string) $mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Could not read screenshot.');
        }

        $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($bytes);

        return $this->callVision($dataUrl);
    }

    /**
     * @return array<string, mixed>
     */
    private function callVision(string $dataUrl): array
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $model = (string) config('crm.communication_check.vision_model', 'gpt-4o-mini');
        $timeout = (int) config('crm.communication_check.vision_timeout', 90);

        $system = <<<'PROMPT'
You extract communication metadata from screenshots for a law-firm CRM audit tool.
Return ONLY valid JSON (no markdown) with this exact shape:
{
  "channel": "email|sms|call|unknown",
  "direction": "incoming|outgoing|unknown",
  "from": string|null,
  "to": string|null,
  "phone": string|null,
  "subject": string|null,
  "snippet": string|null,
  "datetime": string|null,
  "datetime_raw": string|null,
  "app": string|null,
  "extract_confidence": integer 0-100,
  "notes": string|null
}
Rules:
- Prefer email addresses in from/to when visible.
- datetime must be ISO-8601 if you can resolve an absolute time; otherwise null and put the visible text in datetime_raw.
- Do not invent fields that are not visible.
- Do not decide whether staff handled the message.
- If multiple messages appear, extract the most prominent / focused one.
PROMPT;

        $client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => $timeout,
        ]);

        $response = $client->post('chat/completions', [
            'json' => [
                'model' => $model,
                'temperature' => 0,
                'max_tokens' => 800,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Extract communication fields from this screenshot.',
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $dataUrl,
                                    'detail' => 'high',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = json_decode($response->getBody()->getContents(), true);
        $content = (string) ($result['choices'][0]['message']['content'] ?? '');
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            Log::warning('Communication check vision returned non-JSON', ['content' => mb_substr($content, 0, 500)]);
            throw new \RuntimeException('Vision model did not return valid JSON.');
        }

        return $this->normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        $channel = strtolower(trim((string) ($raw['channel'] ?? 'unknown')));
        if (! in_array($channel, ['email', 'sms', 'call', 'unknown'], true)) {
            $channel = 'unknown';
        }

        $direction = strtolower(trim((string) ($raw['direction'] ?? 'unknown')));
        if (! in_array($direction, ['incoming', 'outgoing', 'unknown'], true)) {
            $direction = 'unknown';
        }

        $confidence = (int) ($raw['extract_confidence'] ?? 0);
        $confidence = max(0, min(100, $confidence));

        return [
            'channel' => $channel,
            'direction' => $direction,
            'from' => $this->nullableString($raw['from'] ?? null),
            'to' => $this->nullableString($raw['to'] ?? null),
            'phone' => $this->nullableString($raw['phone'] ?? null),
            'subject' => $this->nullableString($raw['subject'] ?? null),
            'snippet' => $this->nullableString($raw['snippet'] ?? null),
            'datetime' => $this->nullableString($raw['datetime'] ?? null),
            'datetime_raw' => $this->nullableString($raw['datetime_raw'] ?? null),
            'app' => $this->nullableString($raw['app'] ?? null),
            'extract_confidence' => $confidence,
            'notes' => $this->nullableString($raw['notes'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
