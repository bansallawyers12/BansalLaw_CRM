<?php

namespace App\Services\Email;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\PersonalDocumentType;
use App\Models\VisaDocumentType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves matter context and upload-folder options for the Outlook emails UI partial.
 */
class EmailOutlookViewService
{
    public function resolveMatterId(Admin $client, ?string $matterRef, ?int $activeClientMatterId = null): ?int
    {
        if ($activeClientMatterId !== null && $activeClientMatterId > 0) {
            return $activeClientMatterId;
        }

        if ($matterRef !== null && $matterRef !== '') {
            $fromRef = ClientMatter::query()
                ->where('client_id', (int) $client->id)
                ->where('client_unique_matter_no', $matterRef)
                ->value('id');

            if ($fromRef) {
                return (int) $fromRef;
            }
        }

        $latest = ClientMatter::query()
            ->where('client_id', (int) $client->id)
            ->where(function ($q) {
                $q->where('matter_status', 1)->orWhere('matter_status', '1');
            })
            ->orderByDesc('id')
            ->value('id');

        return $latest ? (int) $latest : null;
    }

    /**
     * @return array{
     *     matterId: ?int,
     *     emailUploadPersonalFolders: list<array{id: string, title: string}>,
     *     emailUploadMatterFolders: list<array{id: string, title: string}>
     * }
     */
    public function buildClientTabContext(Admin $client, ?string $matterRef, ?int $activeClientMatterId = null): array
    {
        $matterId = $this->resolveMatterId($client, $matterRef, $activeClientMatterId);

        return [
            'matterId' => $matterId,
            'emailUploadPersonalFolders' => $this->personalFolders((int) $client->id),
            'emailUploadMatterFolders' => $matterId ? $this->matterFolders((int) $client->id, $matterId) : [],
        ];
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    public function personalFolders(int $clientId): array
    {
        if (! Schema::hasTable('personal_document_types')) {
            return [];
        }

        $cacheSeconds = max(60, (int) config('crm.emails.folder_cache_seconds', 300));
        $cacheKey = 'crm.emails.personal_folders.' . $clientId;

        return Cache::remember($cacheKey, $cacheSeconds, function () use ($clientId) {
            return PersonalDocumentType::query()
                ->select('id', 'title')
                ->where('status', 1)
                ->where(function ($query) use ($clientId) {
                    $query->whereNull('client_id')
                        ->orWhere('client_id', $clientId);
                })
                ->orderBy('id')
                ->get()
                ->map(static fn ($row) => ['id' => (string) $row->id, 'title' => (string) $row->title])
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    public function matterFolders(int $clientId, int $matterId): array
    {
        if (! Schema::hasTable('visa_document_types')) {
            return [];
        }

        $cacheSeconds = max(60, (int) config('crm.emails.folder_cache_seconds', 300));
        $cacheKey = 'crm.emails.matter_folders.' . $clientId . '.' . $matterId;

        return Cache::remember($cacheKey, $cacheSeconds, function () use ($clientId, $matterId) {
            return VisaDocumentType::query()
                ->select('id', 'title')
                ->where('status', 1)
                ->where(function ($query) use ($clientId, $matterId) {
                    $query->where(function ($q) {
                        $q->whereNull('client_id')->whereNull('client_matter_id');
                    })->orWhere(function ($q) use ($clientId) {
                        $q->where('client_id', $clientId)->whereNull('client_matter_id');
                    })->orWhere(function ($q) use ($clientId, $matterId) {
                        $q->where('client_id', $clientId)->where('client_matter_id', $matterId);
                    });
                })
                ->orderBy('id')
                ->get()
                ->map(static fn ($row) => ['id' => (string) $row->id, 'title' => (string) $row->title])
                ->values()
                ->all();
        });
    }
}
