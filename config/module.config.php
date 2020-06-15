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
                'Basket',
                'BulkExport',
                'Comment',
                'ContactUs',
                'Correction',
                'Folksonomy',
                'Selection',
            ],
            'item_show' => [
                'AccessResource',
                'Annotation',
                'Basket',
                'Bibliography',
                'BulkExport',
                'Citation',
                'Coins',
                'Collecting',
                'Comment',
                'ContactUs',
                'Contribute',
                'Correction',
                'Diva',
                'Folksonomy',
                'Mapping',
                'MediaQuality',
                'Mirador',
                'Selection',
                'Sharing',
                'UnApi',
                'UniversalViewer',
            ],
            'media_show' => [
                'AccessResource',
                'Annotation',
                'Basket',
                'BulkExport',
                'Comment',
                'ContactUs',
                'Correction',
                'Folksonomy',
                'MediaQuality',
                'Selection',
            ],
            'item_set_browse' => [
                'AccessResource',
                'BulkExport',
                'Mirador',
                'SearchHistory',
                'UniversalViewer',
            ],
            'item_browse' => [
                'AccessResource',
                'BulkExport',
                'Coins',
                'Contribute',
                'Mirador',
                'SearchHistory',
                'UnApi',
                'UniversalViewer',
            ],
            'media_browse' => [
                'BulkExport',
                'SearchHistory',
            ],
        ],
    ],
];
