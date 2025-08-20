<?php

return [
    'file_size' => [
        'max_size' => env('DOCUMENT_MAX_SIZE', 10485760), // 10MB in bytes
        'min_size' => 1024, // 1KB minimum
    ],

    'allowed_formats' => [
        'pdf' => [
            'mime_types' => [
                'application/pdf',
            ],
            'extensions' => ['pdf'],
        ],
        'docx' => [
            'mime_types' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'extensions' => ['docx'],
        ],
        'txt' => [
            'mime_types' => [
                'text/plain',
                'text/txt',
                'application/txt',
            ],
            'extensions' => ['txt'],
        ],
    ],

    'security' => [
        'scan_for_malicious_content' => true,
        'blocked_patterns' => [
            // Potentially dangerous patterns
            '/javascript:/i',
            '/<script/i',
            '/\x00/',
            // PDF header check is handled separately in isSuspiciousBinaryContent
        ],
        'max_file_name_length' => 255,
    ],

    'content_validation' => [
        'min_text_length' => 100, // Minimum characters for meaningful content
        'max_text_length' => 1000000, // 1MB of text
        'legal_keywords' => [
            'договор', 'соглашение', 'контракт', 'сторона', 'сторон',
            'обязательство', 'ответственность', 'права', 'обязанности',
            'исполнение', 'нарушение', 'условия', 'пункт', 'статья',
            'contract', 'agreement', 'party', 'parties', 'obligation',
            'responsibility', 'rights', 'duties', 'performance', 'breach',
            'terms', 'clause', 'article',
        ],
        'min_legal_keyword_matches' => 2, // Minimum legal keywords to consider document legal
    ],

    'rate_limiting' => [
        'max_uploads_per_minute' => 10,
        'max_uploads_per_hour' => 100,
        'max_uploads_per_day' => 500,
    ],

    'encoding' => [
        'supported_encodings' => [
            'UTF-8',
            'UTF-16',
            'UTF-16LE',
            'UTF-16BE',
            'Windows-1251',
            'CP1251',
            'ISO-8859-1',
            'ASCII',
        ],
        'force_utf8_conversion' => true,
    ],

    'performance' => [
        'max_execution_time_per_validator' => 30, // seconds
        'max_memory_limit' => '256M',
        'max_text_content_read' => 1048576, // 1MB for text content extraction
        'performance_warning_threshold_ms' => 5000, // 5 seconds
    ],

    'logging' => [
        'log_validation_start' => true,
        'log_validation_completion' => true,
        'log_validator_execution_time' => true,
        'log_performance_warnings' => true,
        'log_security_issues' => true,
    ],
];