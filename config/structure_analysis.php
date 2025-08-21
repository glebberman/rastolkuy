<?php

declare(strict_types=1);

return [
    'detection' => [
        'min_confidence_threshold' => env('STRUCTURE_MIN_CONFIDENCE', 0.3),
        'min_section_length' => env('STRUCTURE_MIN_SECTION_LENGTH', 50),
        'max_title_length' => env('STRUCTURE_MAX_TITLE_LENGTH', 200),
        'max_analysis_time_seconds' => env('STRUCTURE_MAX_ANALYSIS_TIME', 120),
    ],

    'section_patterns' => [
        'numbered' => [
            '/^(\d+\.?\s*\.?\s?)(.*?)$/um',
            '/^(Раздел\s+\d+\.?\s*\.?\s?)(.*?)$/ium',
            '/^(Глава\s+\d+\.?\s*\.?\s?)(.*?)$/ium',
            '/^(Статья\s+\d+\.?\s*\.?\s?)(.*?)$/ium',
            '/^(§\s*\d+\.?\s*\.?\s?)(.*?)$/um',
        ],
        'subsections' => [
            '/^(\d+\.\d+\.?\s*\.?\s?)(.*?)$/um',
            '/^(\d+\.\d+\.\d+\.?\s*\.?\s?)(.*?)$/um',
        ],
        'named' => [
            '/^(Введение\.?\s*\.?\s?)(.*?)$/ium',
            '/^(Заключение\.?\s*\.?\s?)(.*?)$/ium',
            '/^(Приложение\.?\s*\.?\s?)(.*?)$/ium',
            '/^(Общие\s+положения\.?\s*\.?\s?)(.*?)$/ium',
            '/^(Права\s+и\s+обязанности\.?\s*\.?\s?)(.*?)$/ium',
            '/^(Ответственность\s+сторон\.?\s*\.?\s?)(.*?)$/ium',
            '/^(Заключительные\s+положения\.?\s*\.?\s?)(.*?)$/ium',
        ],
    ],

    'legal_keywords' => [
        'contract_terms' => [
            'договор', 'соглашение', 'контракт', 'сторона', 'стороны',
            'обязательство', 'ответственность', 'права', 'обязанности',
            'исполнение', 'нарушение', 'условия', 'пункт', 'статья',
            'предмет', 'цена', 'оплата', 'срок', 'порядок',
        ],
        'legal_entities' => [
            'заказчик', 'исполнитель', 'подрядчик', 'поставщик',
            'арендатор', 'арендодатель', 'покупатель', 'продавец',
            'работодатель', 'работник', 'клиент', 'компания',
        ],
        'actions' => [
            'поставить', 'выполнить', 'оказать', 'предоставить',
            'передать', 'получить', 'принять', 'подписать',
            'уведомить', 'согласовать', 'утвердить', 'расторгнуть',
        ],
    ],

    'confidence_levels' => [
        'high' => 0.9,
        'medium' => 0.7,
        'low' => 0.5,
    ],

    'anchor_generation' => [
        'prefix' => '<!-- SECTION_ANCHOR_',
        'suffix' => ' -->',
        'max_title_length' => 50,
        'transliteration' => true,
        'normalize_case' => true,
    ],

    'performance' => [
        'max_sections_per_document' => env('STRUCTURE_MAX_SECTIONS', 1000),
        'max_hierarchy_depth' => env('STRUCTURE_MAX_DEPTH', 10),
        'enable_parallel_processing' => env('STRUCTURE_PARALLEL_PROCESSING', false),
        'memory_limit' => env('STRUCTURE_MEMORY_LIMIT', '256M'),
    ],

    'logging' => [
        'log_analysis_start' => true,
        'log_analysis_completion' => true,
        'log_section_detection' => true,
        'log_performance_warnings' => true,
        'log_low_confidence_warnings' => true,
    ],

    'validation' => [
        'require_minimum_sections' => env('STRUCTURE_REQUIRE_MIN_SECTIONS', 1),
        'validate_hierarchy' => true,
        'check_anchor_uniqueness' => true,
        'verify_section_boundaries' => true,
    ],

    'fallback' => [
        'enable_heuristic_detection' => true,
        'merge_short_sections' => true,
        'create_default_section' => true,
        'default_section_title' => 'Основное содержание',
    ],
];
