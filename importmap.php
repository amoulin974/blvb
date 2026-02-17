<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'tom-select/dist/css/tom-select.css' => [
        'version' => '2.5.1',
        'type' => 'css',
    ],
    'tocbot/dist/tocbot.css' => [
        'version' => '4.36.4',
        'type' => 'css',
    ],
    'daisyui' => [
        'version' => '5.5.18',
    ],
    'daisyui/daisyui.min.css' => [
        'version' => '5.5.18',
        'type' => 'css',
    ],
    'tom-select' => [
        'version' => '2.5.1',
    ],
    '@orchidjs/sifter' => [
        'version' => '1.1.0',
    ],
    '@orchidjs/unicode-variants' => [
        'version' => '1.1.2',
    ],
    'tom-select/dist/css/tom-select.default.min.css' => [
        'version' => '2.5.1',
        'type' => 'css',
    ],
    'fullcalendar' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/core/index.js' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/interaction/index.js' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/daygrid/index.js' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/timegrid/index.js' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/list/index.js' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/multimonth/index.js' => [
        'version' => '6.1.20',
    ],
    'preact' => [
        'version' => '10.12.1',
    ],
    'preact/compat' => [
        'version' => '10.12.1',
    ],
    '@fullcalendar/core/internal.js' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/core/preact.js' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/daygrid/internal.js' => [
        'version' => '6.1.20',
    ],
    'preact/hooks' => [
        'version' => '10.12.1',
    ],
    '@fullcalendar/core' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/daygrid' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/interaction' => [
        'version' => '6.1.20',
    ],
    '@fullcalendar/multimonth' => [
        'version' => '6.1.20',
    ],
    'moment' => [
        'version' => '2.30.1',
    ],
    'moment/locale/fr' => [
        'version' => '2.30.1',
    ],
    'tocbot' => [
        'version' => '4.36.4',
    ],
];
