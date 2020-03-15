<?php
namespace BlocksDisposition;

return [
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'blocksdisposition' => [
        'config' => [
            'blocksdisposition_modules' => [],
        ],
        'site_settings' => [
            'blocksdisposition_item_browse' => [],
            'blocksdisposition_item_show' => [],
            'blocksdisposition_item_set_browse' => [],
            'blocksdisposition_item_set_show' => [],
            'blocksdisposition_media_show' => [],
        ],
        // Common modules that trigger "view.show.after" and "view.browse.after".
        // When the module is not set, here or in its own config, the trigger is not executed.
        // TODO Autodetection of the modules that trigger these events (in site settings).
        'views' => [
            'item_set_show' => [
                'Annotation',
                'Comment',
                'ContactUs',
                'Correction',
                'Folksonomy',
            ],
            'item_show' => [
                'AccessResource',
                'Annotation',
                'Basket',
                'Bibliography',
                'Citation',
                'Coins',
                'Collecting',
                'Comment',
                'ContactUs',
                'Correction',
                'Diva',
                'Folksonomy',
                'Mapping',
                'MediaQuality',
                'Mirador',
                'Sharing',
                'UnApi',
                'UniversalViewer',
            ],
            'media_show' => [
                'AccessResource',
                'Annotation',
                'Comment',
                'ContactUs',
                'Correction',
                'Folksonomy',
                'MediaQuality',
            ],
            'item_set_browse' => [
                'AccessResource',
                'Mirador',
                'SearchHistory',
                'UniversalViewer',
            ],
            'item_browse' => [
                'AccessResource',
                'Coins',
                'Mirador',
                'SearchHistory',
                'UnApi',
                'UniversalViewer',
            ],
            'media_browse' => [
                'SearchHistory',
            ],
        ],
    ],
];
