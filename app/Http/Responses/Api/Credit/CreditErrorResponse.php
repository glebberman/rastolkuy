<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreditErrorResponse extends JsonResponse
{
    public static function internalServerError(string $error, string $message): self
    {
        return new self([
            'error' => $error,
            'message' => $message,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function badRequest(string $error, string $message): self
    {
        return new self([
            'error' => $error,
            'message' => $message,
        ], Response::HTTP_BAD_REQUEST);
    }

    public static function forbidden(string $error, string $message): self
    {
        return new self([
            'error' => $error,
            'message' => $message,
        ], Response::HTTP_FORBIDDEN);
    }

    public static function configurationError(string $error, string $message, string $details): self
    {
        return new self([
            'error' => $error,
            'message' => $message,
            'details' => $details,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function invalidConfiguration(string $error, string $message, string $details): self
    {
        return new self([
            'error' => $error,
            'message' => $message,
            'details' => $details,
        ], Response::HTTP_BAD_REQUEST);
    }
}
