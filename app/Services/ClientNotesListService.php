<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Staff;
use App\Support\NoteAttachmentHtml;
use App\Support\NoteDescriptionHtml;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

/**
 * Client Notes tab list queries + HTML cards.
 * Eager-loads authors and caps rows (Documents / Legal Forms pattern).
 */
class ClientNotesListService
{
    /** @var list<string> */
    private const LIST_COLUMNS = [
        'id',
        'user_id',
        'client_id',
        'title',
        'description',
        'type',
        'pin',
        'task_group',
        'matter_id',
        'mobile_number',
        'spend_mins',
        'assigned_to',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array{notes: Collection<int, Note>, has_more: bool, total: int, next_offset: int, limit: int}
     */
    public function fetchNotes(int $clientId, string $type = 'client', int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? (int) config('crm.notes.list_per_page', 30);
        $limit = max(5, min(100, $limit));
        $offset = max(0, $offset);

        $query = Note::query()
            ->select(self::LIST_COLUMNS)
            ->with([
                'user:id,first_name,last_name',
                'attachments',
            ])
            ->where('client_id', $clientId)
            ->whereNull('assigned_to')
            ->where('type', $type)
            ->orderByDesc('pin')
            ->orderByDesc('updated_at');

        $total = (clone $query)->count();
        $notes = $query->skip($offset)->take($limit)->get();
        $nextOffset = $offset + $notes->count();
        $hasMore = $nextOffset < $total;

        return [
            'notes' => $notes,
            'has_more' => $hasMore,
            'total' => $total,
            'next_offset' => $nextOffset,
            'limit' => $limit,
        ];
    }

    /**
     * @param  Collection<int, Note>|iterable<Note>  $notes
     */
    public function renderCards(iterable $notes, ?Staff $actor = null): string
    {
        return View::make('crm.clients.partials.note_cards', [
            'notes' => $notes,
            'actor' => $actor,
        ])->render();
    }

    /**
     * @return array{html: string, has_more: bool, total: int, next_offset: int, limit: int}
     */
    public function fetchAndRender(int $clientId, string $type = 'client', int $offset = 0, ?int $limit = null, ?Staff $actor = null): array
    {
        $fetched = $this->fetchNotes($clientId, $type, $offset, $limit);

        return [
            'html' => $this->renderCards($fetched['notes'], $actor),
            'has_more' => $fetched['has_more'],
            'total' => $fetched['total'],
            'next_offset' => $fetched['next_offset'],
            'limit' => $fetched['limit'],
        ];
    }

    /**
     * @return array{label: string, class: string, inline: string}
     */
    public static function typeMeta(?string $taskGroup): array
    {
        if ($taskGroup === null || $taskGroup === '') {
            return [
                'label' => 'Others',
                'class' => 'note-type-others',
                'inline' => 'others',
            ];
        }

        $type = strtolower($taskGroup);
        $label = 'Others';
        $class = 'note-type-others';
        $inline = 'others';

        if (str_contains($type, 'call')) {
            $label = 'Call';
            $class = 'note-type-call';
            $inline = 'call';
        } elseif (str_contains($type, 'email')) {
            $label = 'Email';
            $class = 'note-type-email';
            $inline = 'email';
        } elseif (str_contains($type, 'in-person')) {
            $label = 'In-Person';
            $class = 'note-type-inperson';
            $inline = 'inperson';
        } elseif (str_contains($type, 'others')) {
            $label = 'Others';
            $class = 'note-type-others';
            $inline = 'others';
        } elseif (str_contains($type, 'attention')) {
            $label = 'Attention';
            $class = 'note-type-attention';
            $inline = 'attention';
        }

        return [
            'label' => $label,
            'class' => $class,
            'inline' => $inline,
        ];
    }

    public static function canEditNoteDatetime(?Staff $actor): bool
    {
        if (! $actor instanceof Staff) {
            return false;
        }

        return $actor->hasEffectiveSuperAdminPrivileges() || (int) $actor->role === 16;
    }
}
