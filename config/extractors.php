<?php

declare(strict_types=1);

use App\Services\Parser\Extractors\TxtExtractor;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for document extraction operations.
    |
    */
    'default_timeout' => env('EXTRACTOR_TIMEOUT', 60),
    'max_file_size' => env('EXTRACTOR_MAX_SIZE', 50 * 1024 * 1024), // 50MB
    'max_pages' => env('EXTRACTOR_MAX_PAGES', 200),
    'async_threshold' => env('EXTRACTOR_ASYNC_THRESHOLD', 20), // pages

    /*
    |--------------------------------------------------------------------------
    | Security Limits
    |--------------------------------------------------------------------------
    |
    | Security and performance limits for content validation.
    |
    */
    'limits' => [
        'max_file_size' => env('EXTRACTOR_MAX_SIZE', 50 * 1024 * 1024), // 50MB
        'max_line_length' => env('EXTRACTOR_MAX_LINE_LENGTH', 10000),
        'max_lines' => env('EXTRACTOR_MAX_LINES', 100000),
        'chunk_size' => env('EXTRACTOR_CHUNK_SIZE', 1024 * 1024), // 1MB
        'stream_threshold' => env('EXTRACTOR_STREAM_THRESHOLD', 10 * 1024 * 1024), // 10MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Element Classification Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for document element classification logic.
    |
    */
    'classification' => [
        'paragraph_min_length' => env('EXTRACTOR_PARAGRAPH_MIN_LENGTH', 50),
        'header_max_length' => env('EXTRACTOR_HEADER_MAX_LENGTH', 100),
        'header_min_font_size' => env('EXTRACTOR_HEADER_MIN_FONT_SIZE', 16),
        'bold_min_font_size' => env('EXTRACTOR_BOLD_MIN_FONT_SIZE', 12),
        'table_min_separators' => env('EXTRACTOR_TABLE_MIN_SEPARATORS', 2),
        'default_confidence' => (float) env('EXTRACTOR_DEFAULT_CONFIDENCE', 0.8),
        'font_sizes' => [
            'h1' => (int) env('EXTRACTOR_H1_FONT_SIZE', 20),
            'h2' => (int) env('EXTRACTOR_H2_FONT_SIZE', 16),
            'h3' => (int) env('EXTRACTOR_H3_FONT_SIZE', 14),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported File Types
    |--------------------------------------------------------------------------
    |
    | Mapping of MIME types to their respective extractor classes.
    |
    */
    'supported_types' => [
        'text/plain' => TxtExtractor::class,
        'text/txt' => TxtExtractor::class,
        'application/txt' => TxtExtractor::class,
        // 'application/pdf' => PdfExtractor::class,
        // 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => DocxExtractor::class,
        // 'application/rtf' => RtfExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Extraction Profiles
    |--------------------------------------------------------------------------
    |
    | Predefined configuration profiles for different use cases.
    |
    */
    'profiles' => [
        'fast' => [
            'preserve_formatting' => false,
            'extract_images' => false,
            'extract_tables' => false,
            'detect_headers' => true,
            'timeout_seconds' => 30,
            'collect_metrics' => false,
        ],
        'detailed' => [
            'preserve_formatting' => true,
            'extract_images' => true,
            'extract_tables' => true,
            'detect_headers' => true,
            'timeout_seconds' => 120,
            'collect_metrics' => true,
        ],
        'large_files' => [
            'preserve_formatting' => false,
            'extract_images' => false,
            'extract_tables' => true,
            'detect_headers' => true,
            'max_pages' => 500,
            'timeout_seconds' => 300,
            'enable_async' => true,
            'collect_metrics' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for performance metrics collection and reporting.
    |
    */
    'metrics' => [
        'enabled' => env('EXTRACTOR_METRICS_ENABLED', true),
        'driver' => env('EXTRACTOR_METRICS_DRIVER', 'log'), // log, statsd, prometheus
        'collect_detailed' => env('EXTRACTOR_METRICS_DETAILED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for extraction process logging.
    |
    */
    'logging' => [
        'level' => env('EXTRACTOR_LOG_LEVEL', 'info'),
        'channel' => env('EXTRACTOR_LOG_CHANNEL', 'extractors'),
        'log_extraction_start' => true,
        'log_extraction_end' => true,
        'log_errors' => true,
        'log_performance' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | File validation settings and limits.
    |
    */
    'validation' => [
        'check_mime_type' => true,
        'allowed_extensions' => ['txt', 'pdf', 'docx', 'rtf'],
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'min_file_size' => 1, // 1 byte
        'check_file_integrity' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize extraction performance.
    |
    */
    'performance' => [
        'memory_limit' => env('EXTRACTOR_MEMORY_LIMIT', '512M'),
        'chunk_size' => env('EXTRACTOR_CHUNK_SIZE', 1024 * 1024), // 1MB
        'enable_caching' => env('EXTRACTOR_ENABLE_CACHING', true),
        'cache_ttl' => env('EXTRACTOR_CACHE_TTL', 3600), // 1 hour
        'max_concurrent_extractions' => env('EXTRACTOR_MAX_CONCURRENT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Text Processing Settings
    |--------------------------------------------------------------------------
    |
    | Settings for text processing and classification.
    |
    */
    'text_processing' => [
        'encoding_detection' => [
            'enabled' => true,
            'supported_encodings' => [
                'UTF-8',
                'UTF-16',
                'Windows-1251',
                'Windows-1252',
                'ISO-8859-1',
                'ISO-8859-5',
                'CP866',
                'KOI8-R',
                'ASCII',
            ],
            'fallback_encoding' => 'UTF-8',
        ],
        'element_classification' => [
            'confidence_threshold' => 0.7,
            'header_detection_enabled' => true,
            'list_detection_enabled' => true,
            'table_detection_enabled' => true,
        ],
    ],
];
