<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\CommunicationCheckRun;
use App\Services\CommunicationCheck\AccountabilityScorer;
use App\Services\CommunicationCheck\CallActionMatcher;
use App\Services\CommunicationCheck\EmailLogMatcher;
use App\Services\CommunicationCheck\PhoneNormalizer;
use App\Services\CommunicationCheck\SmsLogMatcher;
use App\Services\CommunicationCheck\VisionExtractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CommunicationCheckController extends Controller
{
    public function __construct(
        private VisionExtractionService $vision,
        private EmailLogMatcher $emailMatcher,
        private SmsLogMatcher $smsMatcher,
        private CallActionMatcher $callMatcher,
        private AccountabilityScorer $scorer
    ) {
    }

    public function index()
    {
        return view('crm.communication-check.index', [
            'lookbackDefault' => (int) config('crm.communication_check.lookback_days_default', 30),
            'maxFiles' => (int) config('crm.communication_check.max_files', 10),
            'maxFileKb' => (int) config('crm.communication_check.max_file_kb', 5120),
            'followupHours' => (int) config('crm.communication_check.followup_hours', 24),
            'isLocalOnly' => true,
        ]);
    }

    public function analyze(Request $request)
    {
        $maxFiles = (int) config('crm.communication_check.max_files', 10);
        $maxKb = (int) config('crm.communication_check.max_file_kb', 5120);

        $validator = Validator::make($request->all(), [
            'screenshots' => 'required|array|min:1|max:' . $maxFiles,
            'screenshots.*' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:' . $maxKb,
            'lookback_days' => 'nullable|integer|in:7,30,90',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $lookbackDays = (int) ($request->input('lookback_days')
            ?: config('crm.communication_check.lookback_days_default', 30));

        $this->cleanupExpiredBatches();

        $batchToken = (string) Str::uuid();
        $batchDir = $this->batchDirectory($batchToken);
        File::ensureDirectoryExists($batchDir);

        $items = [];
        $failed = [];
        $queriedSummary = [
            'tables' => ['email_logs', 'sms_logs', 'notes', 'activities_logs'],
            'lookback_days' => $lookbackDays,
            'call_window_minutes' => (int) config('crm.communication_check.call_window_minutes', 30),
        ];

        foreach ($request->file('screenshots', []) as $file) {
            $itemId = (string) Str::uuid();
            $originalName = $file->getClientOriginalName();
            $ext = strtolower((string) $file->getClientOriginalExtension() ?: 'jpg');
            $storedName = $itemId . '.' . $ext;

            try {
                $file->move($batchDir, $storedName);
                $storedPath = $batchDir . DIRECTORY_SEPARATOR . $storedName;

                $extracted = $this->vision->extractFromImage($storedPath, $file->getClientMimeType());
                $channel = $this->resolveChannel($extracted);
                $extracted['channel'] = $channel;

                $matchResult = [
                    'candidates' => [],
                    'best' => null,
                    'match_confidence' => 0,
                    'matched_by' => [],
                    'client_suggestions' => [],
                    'search' => [],
                    'inbound_warning' => null,
                    'insufficient_data' => false,
                    'insufficient_reason' => null,
                ];

                if ($channel === 'email') {
                    $matchResult = $this->emailMatcher->match($extracted, $lookbackDays);
                } elseif ($channel === 'sms') {
                    $matchResult = $this->smsMatcher->match($extracted, $lookbackDays);
                } elseif ($channel === 'call') {
                    $matchResult = $this->callMatcher->match($extracted, $lookbackDays);
                }

                $score = $this->scorer->score($extracted, $matchResult);

                $items[] = [
                    'id' => $itemId,
                    'filename' => $originalName,
                    'extracted' => $extracted,
                    'match' => [
                        'candidates' => $matchResult['candidates'] ?? [],
                        'best' => $matchResult['best'] ?? null,
                        'match_confidence' => $matchResult['match_confidence'] ?? 0,
                        'matched_by' => $matchResult['matched_by'] ?? [],
                        'search' => $matchResult['search'] ?? [],
                        'client_suggestions' => $matchResult['client_suggestions'] ?? [],
                        'inbound_warning' => $matchResult['inbound_warning'] ?? null,
                        'insufficient_data' => $matchResult['insufficient_data'] ?? false,
                        'insufficient_reason' => $matchResult['insufficient_reason'] ?? null,
                    ],
                    'score' => $score,
                ];
            } catch (\Throwable $e) {
                @unlink($batchDir . DIRECTORY_SEPARATOR . $storedName);
                $failed[] = [
                    'filename' => $originalName,
                    'error' => $e->getMessage(),
                ];
                Log::error('Communication check analyze failed', [
                    'file' => $originalName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($items === [] && $failed !== []) {
            File::deleteDirectory($batchDir);

            return response()->json([
                'status' => false,
                'message' => 'No screenshots could be analyzed.',
                'failed' => $failed,
            ], 422);
        }

        CommunicationCheckRun::create([
            'user_id' => Auth::id(),
            'batch_token' => $batchToken,
            'lookback_days' => $lookbackDays,
            'file_count' => count($items),
            'extracted' => array_map(static fn (array $i) => [
                'id' => $i['id'],
                'filename' => $i['filename'],
                'extracted' => $i['extracted'],
            ], $items),
            'results' => array_map(static fn (array $i) => [
                'id' => $i['id'],
                'channel' => $i['extracted']['channel'] ?? null,
                'verdict' => $i['score']['verdict'] ?? null,
                'confidence' => $i['score']['confidence'] ?? null,
                'email_log_id' => $i['score']['matched_record']['email_log_id'] ?? null,
                'sms_log_id' => $i['score']['matched_record']['sms_log_id'] ?? null,
                'note_id' => $i['score']['matched_record']['note_id'] ?? null,
                'activity_log_id' => $i['score']['matched_record']['activity_log_id'] ?? null,
                'client_id' => $i['score']['matched_record']['client_id'] ?? null,
            ], $items),
            'queried' => $queriedSummary,
            'storage_path' => $batchDir,
        ]);

        return response()->json([
            'status' => true,
            'message' => count($items) . ' screenshot(s) checked.',
            'batch_token' => $batchToken,
            'lookback_days' => $lookbackDays,
            'items' => $items,
            'failed' => $failed,
            'summary' => [
                'worked' => count(array_filter($items, static fn ($i) => ($i['score']['verdict'] ?? '') === 'worked')),
                'logged' => count(array_filter($items, static fn ($i) => ($i['score']['verdict'] ?? '') === 'logged')),
                'gap' => count(array_filter($items, static fn ($i) => ($i['score']['verdict'] ?? '') === 'gap')),
                'unsupported' => count(array_filter($items, static fn ($i) => ($i['score']['verdict'] ?? '') === 'unsupported')),
            ],
            'disclaimer' => 'Assistive only — confirm in CRM before treating as staff accountability fact. Vision may misread dates and addresses.',
        ]);
    }

    private function batchDirectory(string $token): string
    {
        return storage_path('app/communication-check/' . $token);
    }

    /**
     * Infer channel when vision returns unknown, using extracted fields.
     *
     * @param  array<string, mixed>  $extracted
     */
    private function resolveChannel(array $extracted): string
    {
        $channel = strtolower((string) ($extracted['channel'] ?? 'unknown'));
        if (in_array($channel, ['email', 'sms', 'call'], true)) {
            return $channel;
        }

        $hasEmail = false;
        foreach (['from', 'to'] as $key) {
            $v = (string) ($extracted[$key] ?? '');
            if ($v !== '' && str_contains($v, '@')) {
                $hasEmail = true;
                break;
            }
        }

        $phone = PhoneNormalizer::extractFromText($extracted['phone'] ?? null)
            ?? PhoneNormalizer::extractFromText($extracted['from'] ?? null)
            ?? PhoneNormalizer::extractFromText($extracted['to'] ?? null);

        $subject = trim((string) ($extracted['subject'] ?? ''));
        $app = strtolower((string) ($extracted['app'] ?? ''));

        if ($hasEmail || $subject !== '') {
            return 'email';
        }

        if ($phone
            || str_contains($app, 'message')
            || str_contains($app, 'imessage')
            || str_contains($app, 'sms')) {
            return 'sms';
        }

        if (str_contains($app, 'phone') || str_contains($app, 'recents') || str_contains($app, 'call')) {
            return 'call';
        }

        return 'unknown';
    }

    private function cleanupExpiredBatches(): void
    {
        $hours = (int) config('crm.communication_check.retention_hours', 24);
        $root = storage_path('app/communication-check');
        if (! is_dir($root)) {
            return;
        }

        $cutoff = now()->subHours($hours)->getTimestamp();
        foreach (File::directories($root) as $dir) {
            if (filemtime($dir) < $cutoff) {
                File::deleteDirectory($dir);
            }
        }
    }
}
