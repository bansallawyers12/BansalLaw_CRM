<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class SortableHelper
{
    /**
     * Generate a sortable link (Spatie / Query Builder: sort=col or sort=-col).
     *
     * @param  array<string, string>  $attributes
     */
    public static function link(string $column, ?string $title = null, array $attributes = []): string
    {
        [$url, $currentDirection, $htmlAttributes] = self::buildLinkParts($column, $attributes);

        $class = $attributes['class'] ?? '';
        if ($currentDirection) {
            $class .= ' sort-'.$currentDirection;
        }
        if ($class !== '') {
            $htmlAttributes = ' class="'.trim($class).'"'.$htmlAttributes;
        }

        $displayTitle = htmlspecialchars($title ?: ucfirst(str_replace('_', ' ', $column)), ENT_QUOTES, 'UTF-8');

        return '<a href="'.$url.'"'.$htmlAttributes.'>'.$displayTitle.'</a>';
    }

    /**
     * Generate sortable link with icon.
     *
     * @param  array<string, string>  $attributes
     */
    public static function linkWithIcon(string $column, ?string $title = null, array $attributes = []): string
    {
        [$url, $currentDirection, $htmlAttributes] = self::buildLinkParts($column, $attributes, skipClass: true);

        $class = $attributes['class'] ?? '';
        $icon = 'fa-solid fa-sort';

        if ($currentDirection === 'asc') {
            $icon = 'fa-solid fa-sort-up';
            $class .= ' sort-asc';
        } elseif ($currentDirection === 'desc') {
            $icon = 'fa-solid fa-sort-down';
            $class .= ' sort-desc';
        }

        if ($class !== '') {
            $htmlAttributes = ' class="'.trim($class).'"'.$htmlAttributes;
        }

        $displayTitle = htmlspecialchars($title ?: ucfirst(str_replace('_', ' ', $column)), ENT_QUOTES, 'UTF-8');

        return '<a href="'.$url.'"'.$htmlAttributes.'>'.$displayTitle.' <i class="'.$icon.'"></i></a>';
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array{0: string, 1: string, 2: string}  [url, currentDirection, htmlAttributes]
     */
    private static function buildLinkParts(string $column, array $attributes, bool $skipClass = false): array
    {
        $request = request();
        $currentSort = (string) $request->get('sort', '');
        $currentDirection = '';

        $activeColumn = ltrim($currentSort, '-');
        if ($activeColumn === $column && $currentSort !== '') {
            $currentDirection = str_starts_with($currentSort, '-') ? 'desc' : 'asc';
        }

        $nextSort = $currentDirection === 'asc' ? '-'.$column : $column;

        $queryParams = $request->query();
        $queryParams['sort'] = $nextSort;
        unset($queryParams['page']);

        $url = $request->url().'?'.http_build_query($queryParams);

        $htmlAttributes = '';
        foreach ($attributes as $key => $value) {
            if ($skipClass && $key === 'class') {
                continue;
            }
            if ($key === 'class') {
                continue;
            }
            $htmlAttributes .= ' '.$key.'="'.htmlspecialchars((string) $value).'"';
        }

        return [$url, $currentDirection, $htmlAttributes];
    }

    /**
     * Keep only allow-listed Spatie sort names so invalid ?sort= does not 400
     * and defaultSort still applies.
     *
     * @param  list<string>  $allowedSorts
     */
    public static function requestWithAllowedSortsOnly(array $allowedSorts, ?Request $request = null): Request
    {
        $request ??= request();
        $raw = $request->query('sort', $request->input('sort'));
        if ($raw === null || $raw === '') {
            return $request;
        }

        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        $valid = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $name = ltrim($part, '-');
            if (in_array($name, $allowedSorts, true)) {
                $valid[] = $part;
            }
        }

        $normalized = implode(',', $valid);
        $original = is_array($raw) ? implode(',', $raw) : (string) $raw;
        if ($normalized === $original) {
            return $request;
        }

        $query = $request->query();
        if ($normalized === '') {
            unset($query['sort']);
        } else {
            $query['sort'] = $normalized;
        }

        return $request->duplicate($query);
    }
}
