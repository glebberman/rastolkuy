<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Exception;

class ExtractorErrorResponse extends JsonResponse
{
    public function __construct(Exception $exception)
    {
        $data = [
            'status' => 'error',
            'message' => $exception->getMessage(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
        ];

        parent::__construct($data, 500, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}