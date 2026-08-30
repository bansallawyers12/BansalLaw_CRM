<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PersonalDocumentType;
use App\Models\VisaDocumentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

/**
 * Folder document list queries + HTML partials (list + grid).
 * Keeps unbounded Document::with()->get() + HTML out of ClientDocumentsController.
 */
class ClientDocumentFolderListService
{
    /** @var list<string> */
    private const LIST_COLUMNS = [
        'id',
        'user_id',
        'client_id',
        'client_matter_id',
        'checklist',
        'file_name',
        'filetype',
        'folder_name',
        'myfile',
        'myfile_key',
        'status',
        'doc_type',
        'type',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array{documents: Collection<int, Document>, has_more: bool, total: int}
     */
    public function fetchFolderDocuments(
        int $clientId,
        string $folderName,
        string $doctype,
        string $type = 'client',
        ?int $clientMatterId = null
    ): array {
        $limit = max(0, (int) config('crm.documents.folder_list_limit', 150));

        $query = Document::query()
            ->select(self::LIST_COLUMNS)
            ->with(['staff:id,first_name,last_name'])
            ->where('client_id', $clientId)
            ->whereNull('not_used_doc')
            ->where('type', $type)
            ->where('folder_name', $folderName);

        if (in_array($doctype, ['matter', 'visa'], true)) {
            $query->whereIn('doc_type', ['matter', 'visa']);
            if ($clientMatterId !== null && $clientMatterId !== 0) {
                $query->where('client_matter_id', $clientMatterId);
            }
            $query->orderByDesc('created_at');
        } else {
            $query->where('doc_type', $doctype)->orderByDesc('updated_at');
        }

        $total = (clone $query)->count();
        $hasMore = $limit > 0 && $total > $limit;
        if ($limit > 0) {
            $query->limit($limit);
        }

        return [
            'documents' => $query->get(),
            'has_more' => $hasMore,
            'total' => $total,
        ];
    }

    /**
     * @return array{data: string, griddata: string, has_more: bool, total: int}
     */
    public function renderMatterFolder(int $clientId, string $folderName, string $type = 'client', ?int $clientMatterId = null): array
    {
        $fetched = $this->fetchFolderDocuments($clientId, $folderName, 'matter', $type, $clientMatterId);
        $documents = $fetched['documents'];
        $parentDocs = $documents->filter(
            fn (Document $d) => ! str_ends_with((string) ($d->checklist ?? ''), '_signed')
        )->values();

        $visaTitles = $this->visaTitlesForDocuments($documents);

        return [
            'data' => View::make('crm.clients.partials.matter_document_folder_list', [
                'fetchd' => $parentDocs,
                'folderName' => $folderName,
                'clientMatterId' => $clientMatterId,
                'visaTitles' => $visaTitles,
            ])->render(),
            'griddata' => View::make('crm.clients.partials.matter_document_folder_grid', [
                'fetchd' => $documents,
                'visaTitles' => $visaTitles,
            ])->render(),
            'has_more' => $fetched['has_more'],
            'total' => $fetched['total'],
        ];
    }

    /**
     * @return array{data: string, griddata: string, has_more: bool, total: int}
     */
    public function renderPersonalFolder(int $clientId, string $folderName, string $doctype = 'personal', string $type = 'client'): array
    {
        $fetched = $this->fetchFolderDocuments($clientId, $folderName, $doctype, $type);
        $documents = $fetched['documents'];
        $doccategoryTitle = PersonalDocumentType::where('id', $folderName)->value('title') ?? '';

        return [
            'data' => View::make('crm.clients.partials.personal_document_folder_list', [
                'fetchd' => $documents,
                'clientid' => $clientId,
                'folderName' => $folderName,
                'doccategoryTitle' => $doccategoryTitle,
            ])->render(),
            'griddata' => View::make('crm.clients.partials.personal_document_folder_grid', [
                'fetchd' => $documents,
                'folderName' => $folderName,
                'doccategoryTitle' => $doccategoryTitle,
            ])->render(),
            'has_more' => $fetched['has_more'],
            'total' => $fetched['total'],
        ];
    }

    /**
     * @param  Collection<int, Document>  $documents
     * @return array<string, string>
     */
    public function visaTitlesForDocuments(Collection $documents): array
    {
        $ids = $documents->pluck('folder_name')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return VisaDocumentType::query()
            ->whereIn('id', $ids)
            ->pluck('title', 'id')
            ->mapWithKeys(fn ($title, $id) => [(string) $id => (string) $title])
            ->all();
    }
}
