<?php

declare(strict_types=1);

namespace App\Services\LLM\Exceptions;

use Exception;

/**
 * Base exception for LLM service operations.
 */
class LLMException extends Exception
{
    /**
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        protected array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the context data for this exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Add context data to the exception.
     *
     * @param string $key Context key
     * @param mixed $value Context value
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Convert exception to array for logging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString(),
        ];
    }
}
