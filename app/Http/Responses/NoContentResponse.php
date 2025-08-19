<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class NoContentResponse extends JsonResponse
{
    public function __construct()
    {
        parent::__construct(null, 204);
    }
}