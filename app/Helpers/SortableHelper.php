<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class SortableHelper
{
    /**
     * Generate a sortable link
     *
     * @param string $column
     * @param string $title
     * @param array $attributes
     * @return string
     */
    public static function link(string $column, string $title = null, array $attributes = []): string
    {
        $request = request();
        $currentSort = $request->get('sort', '');
        $currentDirection = '';
        
        // Check if this column is currently being sorted
        if (str_starts_with($currentSort, $column)) {
            $currentDirection = str_starts_with($currentSort, '-') ? 'desc' : 'asc';
        }
        
        // Determine next sort direction
        $nextDirection = $currentDirection === 'asc' ? '-' . $column : $column;
        
        // Build query parameters
        $queryParams = $request->query();
        $queryParams['sort'] = $nextDirection;
        
        // Remove page parameter when sorting
        unset($queryParams['page']);
        
        // Build URL
        $url = $request->url() . '?' . http_build_query($queryParams);
        
        // Build HTML attributes
        $htmlAttributes = '';
        foreach ($attributes as $key => $value) {
            $htmlAttributes .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }
        
        // Add sort indicator class
        $class = $attributes['class'] ?? '';
        if ($currentDirection) {
            $class .= ' sort-' . $currentDirection;
        }
        if ($class) {
            $htmlAttributes = ' class="' . trim($class) . '"' . $htmlAttributes;
        }
        
        // Use column name as title if not provided
        $displayTitle = $title ?: ucfirst(str_replace('_', ' ', $column));
        
        return '<a href="' . $url . '"' . $htmlAttributes . '>' . $displayTitle . '</a>';
    }

    /**
     * Generate sortable link with icon
     *
     * @param string $column
     * @param string $title
     * @param array $attributes
     * @return string
     */
    public static function linkWithIcon(string $column, string $title = null, array $attributes = []): string
    {
        $request = request();
        $currentSort = $request->get('sort', '');
        $currentDirection = '';
        
        // Check if this column is currently being sorted
        if (str_starts_with($currentSort, $column)) {
            $currentDirection = str_starts_with($currentSort, '-') ? 'desc' : 'asc';
        }
        
        // Determine next sort direction
        $nextDirection = $currentDirection === 'asc' ? '-' . $column : $column;
        
        // Build query parameters
        $queryParams = $request->query();
        $queryParams['sort'] = $nextDirection;
        
        // Remove page parameter when sorting
        unset($queryParams['page']);
        
        // Build URL
        $url = $request->url() . '?' . http_build_query($queryParams);
        
        // Build HTML attributes
        $htmlAttributes = '';
        foreach ($attributes as $key => $value) {
            if ($key !== 'class') {
                $htmlAttributes .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        // Add sort indicator class and icon
        $class = $attributes['class'] ?? '';
        $icon = 'fa fa-sort';
        
        if ($currentDirection === 'asc') {
            $icon = 'fa fa-sort-up';
            $class .= ' sort-asc';
        } elseif ($currentDirection === 'desc') {
            $icon = 'fa fa-sort-down';
            $class .= ' sort-desc';
        }
        
        if ($class) {
            $htmlAttributes = ' class="' . trim($class) . '"' . $htmlAttributes;
        }
        
        // Use column name as title if not provided
        $displayTitle = $title ?: ucfirst(str_replace('_', ' ', $column));
        
        return '<a href="' . $url . '"' . $htmlAttributes . '>' . $displayTitle . ' <i class="' . $icon . '"></i></a>';
    }
} 