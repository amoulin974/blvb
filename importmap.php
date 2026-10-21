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
    'tocbot' => [
        'version' => '4.36.4',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.23',
    ],
    'moment' => [
        'version' => '2.31.0',
    ],
    'moment/locale/fr' => [
        'version' => '2.31.0',
    ],
    '@tiptap/core' => [
        'version' => '2.11.5',
    ],
    '@tiptap/starter-kit' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-link' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-image' => [
        'version' => '2.11.5',
    ],
    '@tiptap/pm/state' => [
        'version' => '2.11.5',
    ],
    '@tiptap/pm/view' => [
        'version' => '2.11.5',
    ],
    '@tiptap/pm/keymap' => [
        'version' => '2.11.4',
    ],
    '@tiptap/pm/model' => [
        'version' => '2.11.4',
    ],
    '@tiptap/pm/transform' => [
        'version' => '2.11.4',
    ],
    '@tiptap/pm/commands' => [
        'version' => '2.11.4',
    ],
    '@tiptap/pm/schema-list' => [
        'version' => '2.11.4',
    ],
    '@tiptap/extension-blockquote' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-bold' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-bullet-list' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-code' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-code-block' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-document' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-dropcursor' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-gapcursor' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-hard-break' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-heading' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-history' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-horizontal-rule' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-italic' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-list-item' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-ordered-list' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-paragraph' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-strike' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-text' => [
        'version' => '2.11.5',
    ],
    'linkifyjs' => [
        'version' => '4.2.0',
    ],
    'prosemirror-state' => [
        'version' => '1.4.3',
    ],
    'prosemirror-view' => [
        'version' => '1.42.3',
    ],
    'prosemirror-keymap' => [
        'version' => '1.2.2',
    ],
    'prosemirror-model' => [
        'version' => '1.24.1',
    ],
    'prosemirror-transform' => [
        'version' => '1.10.2',
    ],
    'prosemirror-commands' => [
        'version' => '1.6.2',
    ],
    'prosemirror-schema-list' => [
        'version' => '1.5.0',
    ],
    '@tiptap/pm/dropcursor' => [
        'version' => '2.11.4',
    ],
    '@tiptap/pm/gapcursor' => [
        'version' => '2.11.4',
    ],
    '@tiptap/pm/history' => [
        'version' => '2.11.4',
    ],
    'prosemirror-view/style/prosemirror.min.css' => [
        'version' => '1.42.3',
        'type' => 'css',
    ],
    'w3c-keyname' => [
        'version' => '2.2.7',
    ],
    'orderedmap' => [
        'version' => '2.1.1',
    ],
    'prosemirror-dropcursor' => [
        'version' => '1.8.1',
    ],
    'prosemirror-gapcursor' => [
        'version' => '1.3.2',
    ],
    'prosemirror-history' => [
        'version' => '1.4.1',
    ],
    'rope-sequence' => [
        'version' => '1.3.4',
    ],
    'prosemirror-gapcursor/style/gapcursor.min.css' => [
        'version' => '1.3.2',
        'type' => 'css',
    ],
    '@tiptap/extension-underline' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-highlight' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-subscript' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-superscript' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-table' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-table-row' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-table-header' => [
        'version' => '2.11.5',
    ],
    '@tiptap/extension-table-cell' => [
        'version' => '2.11.5',
    ],
    '@tiptap/pm/tables' => [
        'version' => '2.11.4',
    ],
    'prosemirror-tables' => [
        'version' => '1.6.2',
    ],
    'prosemirror-tables/style/tables.min.css' => [
        'version' => '1.6.2',
        'type' => 'css',
    ],
];
