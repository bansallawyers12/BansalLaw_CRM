<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\View;

/**
 * Renders a single client detail tab pane for lazy HTML loading (reduces first-paint TTFB).
 */
class ClientDetailTabHtmlService
{
    /** @var array<string, string> */
    private const TAB_VIEWS = [
        'personaldetails' => 'crm.clients.tabs.personal_details',
        'activityfeed' => 'crm.clients.tabs.activityfeed_tab',
        'clientaction' => 'crm.clients.tabs.client_task_tab',
        'noteterm' => 'crm.clients.tabs.notes',
        'personaldocuments' => 'crm.clients.tabs.personal_documents',
        'matterdocuments' => 'crm.clients.tabs.matter_documents',
        'legalforms' => 'crm.clients.tabs.legal_forms',
        'account' => 'crm.clients.tabs.account',
        'emails' => 'crm.clients.tabs.emails',
        'notuseddocuments' => 'crm.clients.tabs.not_used_documents',
    ];

    public function __construct(
        private readonly ClientDetailService $detailService
    ) {}

    public function isSupportedTab(string $tab): bool
    {
        return isset(self::TAB_VIEWS[strtolower($tab)]);
    }

    public function isTabVisible(string $tab, array $matterNav): bool
    {
        $slug = strtolower($tab);

        if ($slug === 'matterdocuments') {
            return (bool) ($matterNav['showMatterDocumentsTab'] ?? false);
        }

        if (in_array($slug, ['legalforms', 'account', 'emails'], true)) {
            return (bool) ($matterNav['showMatterBundleTabs'] ?? false);
        }

        return isset(self::TAB_VIEWS[$slug]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(
        int $clientId,
        string $encodeId,
        string $tab,
        ?string $matterRef,
        Admin $fetchedData
    ): array {
        $slug = strtolower($tab);
        $view = self::TAB_VIEWS[$slug] ?? null;
        if ($view === null) {
            abort(404);
        }

        $matterNav = $this->detailService->resolveMatterNavContext($clientId, $matterRef, $fetchedData);
        if (! $this->isTabVisible($slug, $matterNav)) {
            abort(404);
        }

        $resolved = $this->detailService->resolveActiveTab($slug, $matterRef, $clientId);
        $activeTab = $resolved['activeTab'];
        $matterRef = $resolved['matterRef'];
        $payload = $this->detailService->buildViewPayload($clientId, $matterRef, $activeTab, $fetchedData);

        $data = array_merge(
            [
                'fetchedData' => $fetchedData,
                'encodeId' => $encodeId,
                'id1' => $matterRef,
                'activeTab' => $activeTab,
            ],
            $matterNav,
            $payload
        );

        if ($slug === 'personaldetails') {
            $data['suppressPersonalDetailsTagCard'] = true;
        }

        return [
            'view' => $view,
            'data' => $data,
        ];
    }

    public function render(
        int $clientId,
        string $encodeId,
        string $tab,
        ?string $matterRef,
        Admin $fetchedData
    ): string {
        $built = $this->buildViewData($clientId, $encodeId, $tab, $matterRef, $fetchedData);

        return View::make($built['view'], $built['data'])->render();
    }
}
