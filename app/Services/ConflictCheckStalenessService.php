<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientConflictCheck;
use App\Models\ClientConflictParty;
use App\Models\ClientMatterOpposingParty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ConflictCheckStalenessService
{
    public function __construct(
        private readonly ConflictCheckService $conflictCheckService
    ) {}

    /**
     * @return array{
     *   is_stale: bool,
     *   reason: ?string,
     *   parties_updated_at: ?string,
     *   check_parties_snapshot_at: ?string,
     *   check_search_hash: ?string,
     *   current_search_hash: ?string
     * }
     */
    public function evaluateStaleness(
        Admin $client,
        ?int $clientMatterId,
        ?ClientConflictCheck $latestCheck = null
    ): array {
        $reference = $this->resolveReferenceCheck((int) $client->id, $clientMatterId, $latestCheck);

        if (! $reference) {
            return $this->formatResult(false, null, null, null, null);
        }

        return $this->compareAgainstReference($client, $clientMatterId, $reference);
    }

    /**
     * Staleness for saving Clear/Waived — allows save when search was re-run (ack hash matches).
     *
     * @param  array{search_terms: array}  $result
     * @return array{
     *   is_stale: bool,
     *   reason: ?string,
     *   parties_updated_at: ?string,
     *   check_parties_snapshot_at: ?string,
     *   check_search_hash: ?string,
     *   current_search_hash: ?string
     * }
     */
    public function evaluateAgainstPreviousCheck(
        Admin $client,
        ?int $clientMatterId,
        array $result,
        ?string $acknowledgedSearchHash = null
    ): array {
        $reference = $this->findLatestClearOrWaived((int) $client->id, $clientMatterId);

        if (! $reference) {
            $currentHash = $this->conflictCheckService->buildSearchHash($result['search_terms'] ?? []);

            return $this->formatResult(false, null, null, null, $currentHash);
        }

        $staleness = $this->compareAgainstReference($client, $clientMatterId, $reference, $result);

        if (! $staleness['is_stale']) {
            return $staleness;
        }

        $currentHash = $staleness['current_search_hash'];
        if ($acknowledgedSearchHash !== null
            && $acknowledgedSearchHash !== ''
            && $currentHash !== null
            && hash_equals($currentHash, $acknowledgedSearchHash)) {
            return [
                'is_stale' => false,
                'reason' => null,
                'parties_updated_at' => $staleness['parties_updated_at'],
                'check_parties_snapshot_at' => $staleness['check_parties_snapshot_at'],
                'check_search_hash' => $staleness['check_search_hash'],
                'current_search_hash' => $currentHash,
            ];
        }

        return $staleness;
    }

    public function partiesUpdatedAtForMatter(int $clientId, ?int $clientMatterId): ?Carbon
    {
        if ($clientMatterId && Schema::hasTable('client_matter_opposing_parties')) {
            $ts = ClientMatterOpposingParty::query()
                ->where('client_matter_id', $clientMatterId)
                ->max('updated_at');

            if ($ts) {
                return Carbon::parse($ts);
            }
        }

        if (! Schema::hasTable('client_conflict_parties')) {
            return null;
        }

        $query = ClientConflictParty::query()->where('client_id', $clientId);
        if ($clientMatterId) {
            $query->where('client_matter_id', $clientMatterId);
        } else {
            $query->whereNull('client_matter_id');
        }

        $ts = $query->max('updated_at');

        return $ts ? Carbon::parse($ts) : null;
    }

    public function findLatestClearOrWaived(int $clientId, ?int $clientMatterId): ?ClientConflictCheck
    {
        return ClientConflictCheck::query()
            ->where('client_id', $clientId)
            ->forActiveMatter($clientMatterId)
            ->whereIn('outcome', ['clear', 'waived'])
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveReferenceCheck(
        int $clientId,
        ?int $clientMatterId,
        ?ClientConflictCheck $latestCheck
    ): ?ClientConflictCheck {
        if ($latestCheck && in_array($latestCheck->outcome, ['clear', 'waived'], true)) {
            return $latestCheck;
        }

        return $this->findLatestClearOrWaived($clientId, $clientMatterId);
    }

    /**
     * @param  array{search_terms?: array}|null  $result
     * @return array{
     *   is_stale: bool,
     *   reason: ?string,
     *   parties_updated_at: ?string,
     *   check_parties_snapshot_at: ?string,
     *   check_search_hash: ?string,
     *   current_search_hash: ?string
     * }
     */
    private function compareAgainstReference(
        Admin $client,
        ?int $clientMatterId,
        ClientConflictCheck $reference,
        ?array $result = null
    ): array {
        $partiesUpdatedAt = $this->partiesUpdatedAtForMatter((int) $client->id, $clientMatterId);
        $checkSnapshotAt = $reference->parties_snapshot_at;

        if ($result !== null) {
            $currentHash = $this->conflictCheckService->buildSearchHash($result['search_terms'] ?? []);
        } else {
            try {
                $context = $this->conflictCheckService->buildSearchContext($client, $clientMatterId);
                $currentHash = $context['search_hash'];
            } catch (\InvalidArgumentException) {
                $currentHash = null;
            }
        }

        $partiesStale = $this->partiesAreStale($partiesUpdatedAt, $checkSnapshotAt);
        $hashStale = $currentHash !== null
            && $reference->search_hash !== null
            && ! hash_equals($reference->search_hash, $currentHash);

        $isStale = $partiesStale || $hashStale;

        if (! $isStale) {
            return $this->formatResult(
                false,
                null,
                $partiesUpdatedAt,
                $checkSnapshotAt,
                $currentHash,
                $reference->search_hash
            );
        }

        return $this->formatResult(
            true,
            $this->buildReason($reference, $partiesStale, $hashStale),
            $partiesUpdatedAt,
            $checkSnapshotAt,
            $currentHash,
            $reference->search_hash
        );
    }

    private function partiesAreStale(?Carbon $partiesUpdatedAt, ?Carbon $checkSnapshotAt): bool
    {
        if ($partiesUpdatedAt === null) {
            return false;
        }

        if ($checkSnapshotAt === null) {
            return true;
        }

        return $partiesUpdatedAt->gt($checkSnapshotAt);
    }

    private function buildReason(ClientConflictCheck $reference, bool $partiesStale, bool $hashStale): string
    {
        $outcomeLabel = $reference->outcome === 'waived' ? 'Waived' : 'Clear';
        $checkedAt = $reference->checked_at
            ? $reference->checked_at->format('d M Y H:i')
            : 'the last check';

        if ($partiesStale && $hashStale) {
            return "Other parties and client details changed since the last {$outcomeLabel} check on {$checkedAt}. Re-run the conflict search before saving a new outcome.";
        }

        if ($partiesStale) {
            return "Other parties were updated after the last {$outcomeLabel} check on {$checkedAt}. Re-run the conflict search before saving a new outcome.";
        }

        return "Client details changed since the last {$outcomeLabel} check on {$checkedAt}. Re-run the conflict search before saving a new outcome.";
    }

    /**
     * @return array{
     *   is_stale: bool,
     *   reason: ?string,
     *   parties_updated_at: ?string,
     *   check_parties_snapshot_at: ?string,
     *   check_search_hash: ?string,
     *   current_search_hash: ?string
     * }
     */
    private function formatResult(
        bool $isStale,
        ?string $reason,
        ?Carbon $partiesUpdatedAt,
        ?Carbon $checkSnapshotAt,
        ?string $currentHash,
        ?string $checkSearchHash = null
    ): array {
        return [
            'is_stale' => $isStale,
            'reason' => $reason,
            'parties_updated_at' => $partiesUpdatedAt?->toIso8601String(),
            'check_parties_snapshot_at' => $checkSnapshotAt?->toIso8601String(),
            'check_search_hash' => $checkSearchHash,
            'current_search_hash' => $currentHash,
        ];
    }
}
