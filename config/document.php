<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Document Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for document processing, structure analysis,
    | and cost estimation functionality.
    |
    */

    'structure_analysis' => [
        /*
        |--------------------------------------------------------------------------
        | Analysis Timeout
        |--------------------------------------------------------------------------
        |
        | Maximum time in seconds to spend on document structure analysis.
        | If analysis takes longer, it will be terminated and fallback to
        | simple estimation.
        |
        */
        'timeout_seconds' => env('STRUCTURE_ANALYSIS_TIMEOUT', 30),

        /*
        |--------------------------------------------------------------------------
        | Maximum File Size
        |--------------------------------------------------------------------------
        |
        | Maximum file size in MB for structure analysis. Files larger than
        | this limit will skip structure analysis and use simple estimation.
        |
        */
        'max_file_size_mb' => env('STRUCTURE_ANALYSIS_MAX_SIZE', 50),

        /*
        |--------------------------------------------------------------------------
        | Fallback on Error
        |--------------------------------------------------------------------------
        |
        | Whether to fallback to simple cost estimation if structure analysis
        | fails. If disabled, the estimation will fail entirely on analysis errors.
        |
        */
        'fallback_on_error' => env('STRUCTURE_ANALYSIS_FALLBACK', true),

        /*
        |--------------------------------------------------------------------------
        | Minimum Confidence Threshold
        |--------------------------------------------------------------------------
        |
        | Minimum confidence level (0.0 to 1.0) for section detection.
        | Sections with lower confidence will be excluded from results.
        |
        */
        'min_confidence_threshold' => env('STRUCTURE_ANALYSIS_MIN_CONFIDENCE', 0.5),
    ],

    'cost_estimation' => [
        /*
        |--------------------------------------------------------------------------
        | Section Multiplier
        |--------------------------------------------------------------------------
        |
        | Additional cost multiplier per section found in document structure.
        | Formula: base_cost * (1.0 + sections_count * section_multiplier)
        |
        */
        'section_cost_multiplier' => env('COST_SECTION_MULTIPLIER', 0.1),

        /*
        |--------------------------------------------------------------------------
        | Maximum Section Multiplier
        |--------------------------------------------------------------------------
        |
        | Maximum total multiplier that can be applied based on sections.
        | This prevents extremely high costs for documents with many sections.
        |
        */
        'max_section_multiplier' => env('COST_MAX_SECTION_MULTIPLIER', 3.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for document processing queues, workers, and job settings.
    |
    */
    'queue' => [
        /*
        |--------------------------------------------------------------------------
        | Document Analysis Queue
        |--------------------------------------------------------------------------
        |
        | Queue name for document structure analysis jobs. This allows isolation
        | of analysis jobs from other background processing.
        |
        */
        'document_analysis_queue' => env('DOCUMENT_ANALYSIS_QUEUE', 'document-analysis'),

        /*
        |--------------------------------------------------------------------------
        | Document Processing Queue
        |--------------------------------------------------------------------------
        |
        | Queue name for main document processing jobs (LLM processing).
        |
        */
        'document_processing_queue' => env('DOCUMENT_PROCESSING_QUEUE', 'document-processing'),

        /*
        |--------------------------------------------------------------------------
        | Analysis Job Settings
        |--------------------------------------------------------------------------
        |
        | Configuration for document analysis jobs including retries, timeout,
        | and delay settings.
        |
        */
        'analysis_job' => [
            'max_tries' => env('ANALYSIS_JOB_MAX_TRIES', 3),
            'timeout_seconds' => env('ANALYSIS_JOB_TIMEOUT', 300), // 5 minutes
            'retry_after_seconds' => env('ANALYSIS_JOB_RETRY_AFTER', 60),
        ],

        /*
        |--------------------------------------------------------------------------
        | Processing Job Settings
        |--------------------------------------------------------------------------
        |
        | Configuration for main document processing jobs (LLM processing).
        |
        */
        'processing_job' => [
            'max_tries' => env('PROCESSING_JOB_MAX_TRIES', 5),
            'timeout_seconds' => env('PROCESSING_JOB_TIMEOUT', 600), // 10 minutes
            'retry_after_seconds' => env('PROCESSING_JOB_RETRY_AFTER', 120),
        ],
    ],
];
