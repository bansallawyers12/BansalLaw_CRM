<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Font Awesome CDN (single source — loaded via components.font-awesome)
    |--------------------------------------------------------------------------
    */
    'version' => '6.7.2',

    'cdn_url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',

    /*
    |--------------------------------------------------------------------------
    | FA4/5 style prefix → FA6 prefix
    |--------------------------------------------------------------------------
    */
    'style_prefix' => [
        'fa' => 'fa-solid',
        'fas' => 'fa-solid',
        'far' => 'fa-regular',
        'fab' => 'fa-brands',
    ],

    /*
    |--------------------------------------------------------------------------
    | Icon names renamed or removed in FA6 (legacy name => FA6 name)
    |--------------------------------------------------------------------------
    |
    | Applied by FontAwesomeHelper::iconName() / migrateClasses() and the
    | fontawesome:migrate-db-icons artisan command.
    |
    */
    'icon_renames' => [
        // Already applied in earlier PRs
        'clock-o' => 'clock',
        'pencil-alt' => 'pen',
        'trash-alt' => 'trash-can',
        'file-text' => 'file-lines',
        'arrows-alt' => 'up-down-left-right',
        'ellipsis-v' => 'ellipsis-vertical',
        'thumb-tack' => 'thumbtack',
        'plus-circle' => 'circle-plus',

        // Common FA5 → FA6 Free renames (aliases may still work; prefer canonical)
        'external-link-alt' => 'up-right-from-square',
        'calendar-alt' => 'calendar-days',
        'file-alt' => 'file-lines',
        'map-marker-alt' => 'location-dot',
        'check-circle' => 'circle-check',
        'times-circle' => 'circle-xmark',
        'exclamation-triangle' => 'triangle-exclamation',
        'exclamation-circle' => 'circle-exclamation',
        'calendar-times' => 'calendar-xmark',
        'edit' => 'pen-to-square',
        'redo' => 'arrow-rotate-right',
        'redo-alt' => 'arrow-rotate-right',
        'undo-alt' => 'arrow-rotate-left',
        'sign-out-alt' => 'right-from-bracket',
        'sign-in-alt' => 'right-to-bracket',
        'sync-alt' => 'rotate',
        'sync' => 'rotate',
        'archive' => 'box-archive',
        'save' => 'floppy-disk',
        'sticky-note' => 'note-sticky',
        'mobile-alt' => 'mobile-screen-button',
        'user-alt' => 'user-large',
        'home-alt' => 'house',
        'phone-alt' => 'phone',
        'shield-alt' => 'shield-halved',
        'long-arrow-alt-right' => 'arrow-right-long',
        'long-arrow-alt-left' => 'arrow-left-long',
        'long-arrow-alt-up' => 'arrow-up-long',
        'long-arrow-alt-down' => 'arrow-down-long',
        'exchange-alt' => 'right-left',
        'expand-arrows-alt' => 'up-right-and-down-left-from-center',
        'compress-arrows-alt' => 'down-left-and-up-right-to-center',
        'level-up-alt' => 'turn-up',
        'level-down-alt' => 'turn-down',
        'search-plus' => 'magnifying-glass-plus',
        'search-minus' => 'magnifying-glass-minus',
        'cloud-upload-alt' => 'cloud-arrow-up',
        'cloud-download-alt' => 'cloud-arrow-down',
        'external-link-square-alt' => 'square-up-right',
        'arrows-alt-h' => 'left-right',
        'arrows-alt-v' => 'up-down',
        'comment-alt' => 'comment',
        'window-close' => 'rectangle-xmark',
        'times' => 'xmark',
        'hand-holding-usd' => 'hand-holding-dollar',
        'info-circle' => 'circle-info',
        'question-circle' => 'circle-question',
        'th-large' => 'table-cells-large',
        'tasks' => 'list-check',
        'cog' => 'gear',
        'cogs' => 'gears',
        'stream' => 'bars-staggered',
        'file-upload' => 'file-arrow-up',
        'file-download' => 'file-arrow-down',
        'university' => 'building-columns',
        'home' => 'house',
        'rupee-sign' => 'indian-rupee-sign',
        'file-archive' => 'file-zipper',
    ],

];
