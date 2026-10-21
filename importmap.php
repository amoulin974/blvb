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
    'prosemirror-view' => [
        'version' => '1.42.6',
    ],
    'prosemirror-model' => [
        'version' => '1.25.12',
    ],
    'prosemirror-state' => [
        'version' => '1.4.4',
    ],
    'prosemirror-keymap' => [
        'version' => '1.2.3',
    ],
    'prosemirror-tables' => [
        'version' => '1.8.5',
    ],
    'prosemirror-history' => [
        'version' => '1.5.1',
    ],
    'prosemirror-commands' => [
        'version' => '1.7.2',
    ],
    'prosemirror-changeset' => [
        'version' => '2.4.4',
    ],
    'prosemirror-gapcursor' => [
        'version' => '1.4.1',
    ],
    'prosemirror-transform' => [
        'version' => '1.12.2',
    ],
    'prosemirror-dropcursor' => [
        'version' => '1.8.4',
    ],
    'prosemirror-inputrules' => [
        'version' => '1.5.1',
    ],
    'prosemirror-schema-list' => [
        'version' => '1.5.1',
    ],
    'linkifyjs' => [
        'version' => '4.3.3',
    ],
    'orderedmap' => [
        'version' => '2.1.1',
    ],
    'w3c-keyname' => [
        'version' => '2.2.8',
    ],
    'rope-sequence' => [
        'version' => '1.3.4',
    ],
    '@tiptap/core' => [
        'version' => '3.31.4',
    ],
    '@tiptap/starter-kit' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-image' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-highlight' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-subscript' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-superscript' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-table' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/state' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/view' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/transform' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/commands' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/model' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/schema-list' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/keymap' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/history' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-blockquote' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-bold' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-code' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-code-block' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-document' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-hard-break' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-heading' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-horizontal-rule' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-italic' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-link' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-list' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-paragraph' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-strike' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-text' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extension-underline' => [
        'version' => '3.31.4',
    ],
    '@tiptap/extensions' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/tables' => [
        'version' => '3.31.4',
    ],
    'prosemirror-view/style/prosemirror.min.css' => [
        'version' => '1.42.6',
        'type' => 'css',
    ],
    'prosemirror-tables/style/tables.min.css' => [
        'version' => '1.8.5',
        'type' => 'css',
    ],
    'prosemirror-gapcursor/style/gapcursor.min.css' => [
        'version' => '1.4.1',
        'type' => 'css',
    ],
    '@tiptap/core/jsx-runtime' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/dropcursor' => [
        'version' => '3.31.4',
    ],
    '@tiptap/pm/gapcursor' => [
        'version' => '3.31.4',
    ],
];
