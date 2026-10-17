<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Document;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves matter/personal/ad-hoc document bytes to a local PDF path for public signing.
 */
class DocumentSigningFileResolver
{
    public function resolveReadablePdf(Document $document): ?array
    {
        $durable = app(CrmDurableStorage::class);

        $clientCode = null;
        if ($document->client_id) {
            $admin = Admin::query()->select('client_id')->where('id', $document->client_id)->first();
            $clientCode = $admin?->client_id;
        }

        $candidateKeys = $durable->resolveCandidateKeysFromDocument($document, $clientCode !== null ? (string) $clientCode : null);
        foreach ($this->expandMatterVisaAliases($candidateKeys) as $key) {
            $readable = $durable->resolveReadablePath($key, 'pdf');
            if ($readable !== null && is_file($readable['path']) && filesize($readable['path']) > 0) {
                return $readable;
            }
        }

        $url = (string) ($document->myfile ?? '');
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $urlPath = parse_url($url, PHP_URL_PATH);
            if (is_string($urlPath) && str_contains($urlPath, '/storage/')) {
                $relativeStoragePath = ltrim(substr($urlPath, strpos($urlPath, '/storage/') + 9), '/');
                $decodedPath = urldecode($relativeStoragePath);
                if (Storage::disk('public')->exists($decodedPath)) {
                    $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                    file_put_contents($tmpPdfPath, Storage::disk('public')->get($decodedPath));

                    return ['path' => $tmpPdfPath, 'temporary' => true];
                }
                $localPath = storage_path('app/public/' . $decodedPath);
                if (is_file($localPath) && filesize($localPath) > 0) {
                    return ['path' => $localPath, 'temporary' => false];
                }
            }

            try {
                $httpResponse = Http::timeout(15)->get($url);
                if ($httpResponse->successful() && strlen($httpResponse->body()) > 0) {
                    $tmpPdfPath = storage_path('app/tmp_' . uniqid() . '.pdf');
                    file_put_contents($tmpPdfPath, $httpResponse->body());

                    return ['path' => $tmpPdfPath, 'temporary' => true];
                }
            } catch (\Throwable $e) {
                Log::debug('DocumentSigningFileResolver HTTP fetch failed', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($url !== '') {
            $cleanUrl = ltrim($url, '/');
            foreach ([
                storage_path('app/public/' . $cleanUrl),
                storage_path('app/' . $cleanUrl),
                public_path($cleanUrl),
            ] as $localPath) {
                if (is_file($localPath) && filesize($localPath) > 0) {
                    return ['path' => $localPath, 'temporary' => false];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function expandMatterVisaAliases(array $keys): array
    {
        $expanded = [];
        foreach ($keys as $key) {
            $expanded[] = $key;
            if (str_contains($key, '/matter/')) {
                $expanded[] = str_replace('/matter/', '/visa/', $key);
            } elseif (str_contains($key, '/visa/')) {
                $expanded[] = str_replace('/visa/', '/matter/', $key);
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }

}
