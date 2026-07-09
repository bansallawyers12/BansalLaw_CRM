<?php

namespace App\Helpers;

class FontAwesomeHelper
{
    /**
     * Map a legacy FA4/5 style prefix to FA6 (fa, fas, far, fab).
     */
    public static function stylePrefix(string $legacyPrefix): string
    {
        return config('font_awesome.style_prefix.'.$legacyPrefix, 'fa-solid');
    }

    /**
     * Resolve icon name (apply FA6 renames when configured).
     */
    public static function iconName(string $name): string
    {
        return config('font_awesome.icon_renames.'.$name, $name);
    }

    /**
     * Build FA6 icon class string: e.g. fa-solid fa-arrow-left fa-spin.
     *
     * @param  'solid'|'regular'|'brands'  $style
     */
    public static function iconClass(string $style, string $icon, bool $spin = false, string $extra = ''): string
    {
        $prefix = match ($style) {
            'regular' => 'fa-regular',
            'brands' => 'fa-brands',
            default => 'fa-solid',
        };

        $classes = array_filter([
            $prefix,
            'fa-'.self::iconName($icon),
            $spin ? 'fa-spin' : null,
            $extra !== '' ? $extra : null,
        ]);

        return implode(' ', $classes);
    }

    /**
     * Convert legacy markup class list (fa-solid fa-x, fa-solid fa-x, fa-regular fa-x) to FA6 classes.
     */
    public static function migrateClasses(string $classes): string
    {
        $parts = preg_split('/\s+/', trim($classes)) ?: [];
        $legacyPrefix = null;
        $icon = null;
        $rest = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (in_array($part, ['fa', 'fas', 'far', 'fab'], true)) {
                $legacyPrefix = $part;

                continue;
            }
            if (str_starts_with($part, 'fa-') && $icon === null && ! in_array($part, ['fa-spin', 'fa-fw', 'fa-lg', 'fa-xs', 'fa-sm', 'fa-1x', 'fa-2x', 'fa-3x', 'fa-4x', 'fa-5x'], true)) {
                $icon = substr($part, 3);

                continue;
            }
            $rest[] = $part;
        }

        if ($legacyPrefix === null || $icon === null) {
            return $classes;
        }

        $style = match ($legacyPrefix) {
            'far' => 'regular',
            'fab' => 'brands',
            default => 'solid',
        };

        $spin = in_array('fa-spin', $parts, true);
        $modifiers = array_values(array_filter($rest, fn ($c) => $c !== 'fa-spin'));

        return self::iconClass($style, $icon, $spin, implode(' ', $modifiers));
    }
}
