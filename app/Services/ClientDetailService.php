<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;

/**
 * Builds client/lead detail view payload with tab-aware loading so first paint
 * does not pull account ledgers, conflict graphs, or lead staff lists unless needed.
 */
class ClientDetailService
{
    /** @var list<string> */
    private const KNOWN_TAB_NAMES = [
        'personaldetails', 'companydetails', 'activityfeed', 'noteterm', 'personaldocuments', 'matterdocuments',
        'emails', 'client_portal', 'legalforms',
        'overview', 'documents', 'clientaction',
        'formgenerations', 'formgenerationsl',
        'workflow', 'checklists', 'account', 'notuseddocuments',
        'visadocuments',
    ];

    /** @var list<string> Tabs that include conflict / lead pipeline partials */
    private const CONFLICT_TAB_SLUGS = ['personaldetails', 'overview', 'companydetails'];

    /**
     * Normalize URL segments into active tab and matter ref.
     *
     * @return array{activeTab: string, matterRef: ?string}
     */
    public function resolveActiveTab(?string $tab, ?string $matterRef, int $clientId): array
    {
        if ($matterRef && in_array(strtolower($matterRef), self::KNOWN_TAB_NAMES, true)) {
            if (empty($tab)) {
                $tab = $matterRef;
            }
            $matterRef = null;
        }

        if ($tab !== null && strtolower((string) $tab) === 'visadocuments') {
            $tab = 'matterdocuments';
        }

        $activeTab = $tab ?? 'personaldetails';
        if (strtolower((string) $activeTab) === 'client_portal') {
            $activeTab = 'workflow';
        }

        $slug = strtolower((string) $activeTab);
        if ($slug === 'overview') {
            $activeTab = 'personaldetails';
        } elseif ($slug === 'documents') {
            $activeTab = 'personaldocuments';
        } elseif (in_array($slug, ['workflow', 'checklists'], true)) {
            $activeTab = 'clientaction';
        }

        if (strtolower((string) $activeTab) === 'matterdocuments') {
            $activeCount = ClientMatter::query()
                ->where('client_id', $clientId)
                ->where('matter_status', 1)
                ->count();
            if ($activeCount < 1) {
                $convertedClientKeepsMatterDocsTab = Admin::query()
                    ->where('id', $clientId)
                    ->where('type', 'client')
                    ->where('lead_status', 'converted')
                    ->exists();
                if (! $convertedClientKeepsMatterDocsTab) {
                    $activeTab = 'personaldetails';
                }
            }
        }

        if ($matterRef !== null && $matterRef !== '' && preg_match('/^bank_/i', (string) $matterRef) === 1
            && strtolower((string) $activeTab) === 'matterdocuments') {
            $activeTab = 'personaldetails';
        }

        return [
            'activeTab' => $activeTab,
            'matterRef' => ($matterRef !== null && $matterRef !== '') ? (string) $matterRef : null,
        ];
    }

    public function loadClientRecord(int $clientId): ?Admin
    {
        $record = Admin::query()->find($clientId);
        if (! $record) {
            return null;
        }

        if ($record->is_company) {
            $record->load([
                'company.contactPerson',
                'company.tradingNames',
                'company.directors.directorClient',
            ]);
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewPayload(int $clientId, ?string $matterRef, string $activeTab, Admin $fetchedData): array
    {
        if ($fetchedData->is_company && strtolower((string) $activeTab) === 'companydetails') {
            $activeTab = 'personaldetails';
        }

        $tabSlug = strtolower((string) $activeTab);
        $isLead = ($fetchedData->type ?? '') === 'lead'
            || ($fetchedData->type ?? null) === 1
            || in_array(trim((string) ($fetchedData->type ?? '')), ['lead', 'l', '1'], true);

        $matterContext = $this->resolveMatterContext($clientId, $matterRef);
        $leadBundle = $this->needsLeadBundle($tabSlug, $isLead)
            ? $this->buildLeadBundle($clientId, $fetchedData)
            : $this->emptyLeadBundle($fetchedData);

        $conflictBundle = $this->needsConflictBundle($tabSlug)
            ? $this->buildConflictBundle($clientId, $matterContext['activeClientMatterId'], $fetchedData)
            : $this->emptyConflictBundle();

        $accountTabData = $this->buildAccountTabData($clientId, $tabSlug, $matterContext['activeClientMatterId'], $fetchedData);

        return array_merge(
            [
                'activeTab' => $activeTab,
                'id1' => $matterRef,
            ],
            $matterContext,
            $leadBundle,
            $conflictBundle,
            [
                'accountTabData' => $accountTabData,
                'clientAddresses' => collect(),
                'clientContacts' => collect(),
                'emails' => collect(),
            ]
        );
    }

    /**
     * @return array{
     *     selectedClientMatter: ?ClientMatter,
     *     isClosedMatterView: bool,
     *     activeClientMatterId: ?int
     * }
     */
    private function resolveMatterContext(int $clientId, ?string $matterRef): array
    {
        $selectedClientMatter = null;
        $isClosedMatterView = false;

        if ($matterRef !== null && $matterRef !== '') {
            $selectedClientMatter = ClientMatter::query()
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRef)
                ->with(['matter', 'workflowStage'])
                ->first();
            if ($selectedClientMatter) {
                $isClosedMatterView = ClientMatter::isClosed($selectedClientMatter);
            }
        }

        $activeClientMatterId = null;
        if ($selectedClientMatter) {
            $activeClientMatterId = (int) $selectedClientMatter->id;
        } elseif ($matterRef === null || $matterRef === '') {
            $activeClientMatterId = \App\Support\MatterOtherPartiesHelper::resolveClientMatterId($clientId, null, null);
        }

        return [
            'selectedClientMatter' => $selectedClientMatter,
            'isClosedMatterView' => $isClosedMatterView,
            'activeClientMatterId' => $activeClientMatterId,
        ];
    }

    private function needsLeadBundle(string $tabSlug, bool $isLead): bool
    {
        return $isLead && in_array($tabSlug, self::CONFLICT_TAB_SLUGS, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLeadBundle(int $clientId, Admin $fetchedData): array
    {
        return [
            'assignableStaff' => Staff::query()
                ->where('status', 1)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'leadStageLabels' => [
                'new' => 'New Enquiry',
                'initial_consultation' => 'Initial Consultation',
                'conflict_check' => 'Conflict Check',
                'engaged' => 'Engaged',
                'retained' => 'Retained',
                'follow_up' => 'Follow Up',
                'not_proceeding' => 'Not Proceeding',
                'declined' => 'Declined',
            ],
            'matterFormForLead' => app(ClientEditService::class)->getMatterFormForAddMatter($clientId),
            '__crmEditLeadType' => (($fetchedData->type ?? null) === 1
                || in_array(trim((string) ($fetchedData->type ?? '')), ['lead', 'l', '1'], true)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLeadBundle(Admin $fetchedData): array
    {
        return [
            'assignableStaff' => collect(),
            'leadStageLabels' => [],
            'matterFormForLead' => null,
            '__crmEditLeadType' => (($fetchedData->type ?? null) === 1
                || in_array(trim((string) ($fetchedData->type ?? '')), ['lead', 'l', '1'], true)),
        ];
    }

    private function needsConflictBundle(string $tabSlug): bool
    {
        return in_array($tabSlug, self::CONFLICT_TAB_SLUGS, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConflictBundle(int $clientId, ?int $activeClientMatterId, Admin $fetchedData): array
    {
        $conflictParties = \App\Support\MatterOtherPartiesHelper::loadDisplayParties($clientId, $activeClientMatterId);

        $latestConflictCheck = \App\Models\ClientConflictCheck::query()
            ->where('client_id', $clientId)
            ->forActiveMatter($activeClientMatterId)
            ->with('clientMatter')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->first();

        $conflictCheckHistory = \App\Models\ClientConflictCheck::query()
            ->where('client_id', $clientId)
            ->forActiveMatter($activeClientMatterId)
            ->with('clientMatter')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $referenceClear = $latestConflictCheck
            && in_array($latestConflictCheck->outcome, ['clear', 'waived'], true)
            ? $latestConflictCheck
            : \App\Models\ClientConflictCheck::query()
                ->where('client_id', $clientId)
                ->forActiveMatter($activeClientMatterId)
                ->whereIn('outcome', ['clear', 'waived'])
                ->orderByDesc('checked_at')
                ->orderByDesc('id')
                ->first();

        $conflictCheckStaleness = ['is_stale' => false, 'reason' => null];
        if ($referenceClear) {
            $conflictCheckStaleness = app(ConflictCheckStalenessService::class)
                ->evaluateStaleness($fetchedData, $activeClientMatterId, $referenceClear);
        }

        $partiesUpdatedAt = app(ConflictCheckStalenessService::class)
            ->partiesUpdatedAtForMatter($clientId, $activeClientMatterId);

        return [
            'conflictParties' => $conflictParties,
            'latestConflictCheck' => $latestConflictCheck,
            'conflictCheckHistory' => $conflictCheckHistory,
            'conflictCheckStaleness' => $conflictCheckStaleness,
            'partiesUpdatedAt' => $partiesUpdatedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyConflictBundle(): array
    {
        return [
            'conflictParties' => collect(),
            'latestConflictCheck' => null,
            'conflictCheckHistory' => collect(),
            'conflictCheckStaleness' => ['is_stale' => false, 'reason' => null],
            'partiesUpdatedAt' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccountTabData(int $clientId, string $tabSlug, ?int $activeClientMatterId, Admin $fetchedData): array
    {
        $shell = [
            'clientMatterId' => $activeClientMatterId,
            'trustBalance' => 0.0,
            'outstandingBalance' => 0.0,
            'invoicedTotal' => 0.0,
            'costsDisclosure' => null,
            'exceedsDisclosure' => false,
            'trustRows' => collect(),
            'invoiceRows' => collect(),
            'officeRows' => collect(),
            'documentsById' => collect(),
            'loaded' => false,
        ];

        if ($tabSlug !== 'account') {
            return $shell;
        }

        $accountNavIsLead = (($fetchedData->type ?? null) === 1)
            || in_array(strtolower(trim((string) ($fetchedData->type ?? ''))), ['lead', 'l', '1'], true);
        $accountShowForConvertedClient = ! $accountNavIsLead
            && strtolower(trim((string) ($fetchedData->lead_status ?? ''))) === 'converted';
        $accountMatterExists = ClientMatter::query()
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->where('matter_status', 1)->orWhere('matter_status', '1');
            })
            ->exists();

        if (! $accountMatterExists && ! $accountShowForConvertedClient && $activeClientMatterId === null) {
            return $shell;
        }

        $built = app(ClientAccountTabService::class)->build($clientId, $activeClientMatterId);
        $built['loaded'] = true;

        return $built;
    }
}
