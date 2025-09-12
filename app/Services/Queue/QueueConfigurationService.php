<?php

declare(strict_types=1);

namespace App\Services\Queue;

use InvalidArgumentException;

class QueueConfigurationService
{
    /**
     * Получить название очереди для анализа документов.
     */
    public function getDocumentAnalysisQueue(): string
    {
        $queueName = config('document.queue.document_analysis_queue', 'document-analysis');
        
        if (!is_string($queueName)) {
            throw new InvalidArgumentException('Document analysis queue name must be a string');
        }
        
        return $queueName;
    }
    
    /**
     * Получить название очереди для обработки документов.
     */
    public function getDocumentProcessingQueue(): string
    {
        $queueName = config('document.queue.document_processing_queue', 'document-processing');
        
        if (!is_string($queueName)) {
            throw new InvalidArgumentException('Document processing queue name must be a string');
        }
        
        return $queueName;
    }
    
    /**
     * Получить конфигурацию для задач анализа структуры.
     */
    public function getAnalysisJobConfig(): array
    {
        $maxTries = config('document.queue.analysis_job.max_tries', 3);
        $timeoutSeconds = config('document.queue.analysis_job.timeout_seconds', 300);
        $retryAfterSeconds = config('document.queue.analysis_job.retry_after_seconds', 60);
        
        if (!is_numeric($maxTries)) {
            throw new InvalidArgumentException('Analysis job max_tries must be numeric');
        }
        
        if (!is_numeric($timeoutSeconds)) {
            throw new InvalidArgumentException('Analysis job timeout_seconds must be numeric');
        }
        
        if (!is_numeric($retryAfterSeconds)) {
            throw new InvalidArgumentException('Analysis job retry_after_seconds must be numeric');
        }
        
        return [
            'max_tries' => (int) $maxTries,
            'timeout_seconds' => (int) $timeoutSeconds,
            'retry_after_seconds' => (int) $retryAfterSeconds,
        ];
    }
    
    /**
     * Получить конфигурацию для задач обработки документов.
     */
    public function getProcessingJobConfig(): array
    {
        $maxTries = config('document.queue.processing_job.max_tries', 5);
        $timeoutSeconds = config('document.queue.processing_job.timeout_seconds', 600);
        $retryAfterSeconds = config('document.queue.processing_job.retry_after_seconds', 120);
        
        if (!is_numeric($maxTries)) {
            throw new InvalidArgumentException('Processing job max_tries must be numeric');
        }
        
        if (!is_numeric($timeoutSeconds)) {
            throw new InvalidArgumentException('Processing job timeout_seconds must be numeric');
        }
        
        if (!is_numeric($retryAfterSeconds)) {
            throw new InvalidArgumentException('Processing job retry_after_seconds must be numeric');
        }
        
        return [
            'max_tries' => (int) $maxTries,
            'timeout_seconds' => (int) $timeoutSeconds,
            'retry_after_seconds' => (int) $retryAfterSeconds,
        ];
    }
}