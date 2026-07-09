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
     * Convert legacy markup class list to FA6 classes.
     *
     * Handles:
     * - fa/fas/far/fab + fa-{icon}
     * - already-migrated fa-solid/fa-regular/fa-brands + fa-{legacy-icon}
     */
    public static function migrateClasses(string $classes): string
    {
        $parts = preg_split('/\s+/', trim($classes)) ?: [];
        $legacyPrefix = null;
        $modernPrefix = null;
        $icon = null;
        $rest = [];

        $utility = [
            'fa-spin', 'fa-fw', 'fa-lg', 'fa-xs', 'fa-sm',
            'fa-1x', 'fa-2x', 'fa-3x', 'fa-4x', 'fa-5x',
            'fa-beat', 'fa-fade', 'fa-beat-fade', 'fa-bounce',
            'fa-flip', 'fa-shake', 'fa-pulse', 'fa-flip-horizontal',
            'fa-flip-vertical', 'fa-rotate-90', 'fa-rotate-180', 'fa-rotate-270',
        ];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (in_array($part, ['fa', 'fas', 'far', 'fab'], true)) {
                $legacyPrefix = $part;

                continue;
            }
            if (in_array($part, ['fa-solid', 'fa-regular', 'fa-brands', 'fa-light', 'fa-thin', 'fa-duotone'], true)) {
                $modernPrefix = $part;

                continue;
            }
            if (str_starts_with($part, 'fa-') && $icon === null && ! in_array($part, $utility, true)) {
                $icon = substr($part, 3);

                continue;
            }
            $rest[] = $part;
        }

        if ($icon === null) {
            return $classes;
        }

        $renamed = self::iconName($icon);

        if ($legacyPrefix !== null) {
            $style = match ($legacyPrefix) {
                'far' => 'regular',
                'fab' => 'brands',
                default => 'solid',
            };
            $spin = in_array('fa-spin', $parts, true);
            $modifiers = array_values(array_filter($rest, fn ($c) => $c !== 'fa-spin'));

            return self::iconClass($style, $icon, $spin, implode(' ', $modifiers));
        }

        if ($modernPrefix !== null) {
            $out = array_filter(array_merge(
                [$modernPrefix, 'fa-'.$renamed],
                $rest
            ));

            return implode(' ', $out);
        }

        // Icon-only token (e.g. "fa-file-lines" stored without style prefix)
        if ($renamed !== $icon) {
            return implode(' ', array_filter(array_merge(['fa-'.$renamed], $rest)));
        }

        return $classes;
    }
}
