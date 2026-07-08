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
    */
    'icon_renames' => [
        'clock-o' => 'clock',
        'pencil-alt' => 'pen',
        'trash-alt' => 'trash-can',
        'file-text' => 'file-lines',
        'arrows-alt' => 'up-down-left-right',
        'ellipsis-v' => 'ellipsis-vertical',
        'thumb-tack' => 'thumbtack',
        'plus-circle' => 'circle-plus',
    ],

];
