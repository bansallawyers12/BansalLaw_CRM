<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class UiManualTestCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sections(): array
    {
        $sections = config('ui_manual_tests.sections', []);
        $clientDetailBase = self::clientDetailBaseUrl();

        $resolved = [];
        foreach ($sections as $section) {
            $items = [];
            foreach ($section['items'] ?? [] as $item) {
                $item['href'] = self::resolveHref($item, $clientDetailBase);
                $items[] = $item;
            }
            $section['items'] = $items;
            $resolved[] = $section;
        }

        return $resolved;
    }

    public static function storageKey(): string
    {
        return (string) config('ui_manual_tests.storage_key', 'bansal_crm_ui_manual_tests_v1');
    }

    private static function clientDetailBaseUrl(): ?string
    {
        $configured = config('ui_manual_tests.client_detail_url');
        if (is_string($configured) && $configured !== '') {
            return str_starts_with($configured, 'http') ? $configured : url($configured);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function resolveHref(array $item, ?string $clientDetailBase): ?string
    {
        if (! empty($item['client_detail_tab']) && $clientDetailBase) {
            $tab = (string) $item['client_detail_tab'];
            $base = rtrim($clientDetailBase, '/');
            $knownTabs = [
                'personaldetails', 'overview', 'activityfeed', 'clientaction', 'noteterm',
                'personaldocuments', 'matterdocuments', 'documents', 'emails', 'legalforms',
                'account', 'notuseddocuments', 'application', 'companydetails',
            ];
            $parts = explode('/', $base);
            $last = (string) end($parts);
            if (in_array(strtolower($last), $knownTabs, true)) {
                array_pop($parts);

                return implode('/', $parts).'/'.$tab;
            }

            return $base.'/'.$tab;
        }

        if (! empty($item['route']) && Route::has($item['route'])) {
            $params = $item['route_params'] ?? [];

            return route($item['route'], $params);
        }

        if (! empty($item['path'])) {
            return url((string) $item['path']);
        }

        return null;
    }
}
