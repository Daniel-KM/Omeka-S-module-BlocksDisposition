<?php
namespace BlocksDisposition;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
        ],
    ],
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
            'blocksdisposition_item_browse' => null,
            'blocksdisposition_item_show' => null,
            'blocksdisposition_item_set_browse' => null,
            'blocksdisposition_item_set_show' => null,
            'blocksdisposition_media_show' => null,
        ],
    ],
];
