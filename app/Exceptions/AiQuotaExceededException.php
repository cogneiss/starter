<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by App\Ai\Middleware\EnforceQuota before a prompt reaches a provider.
 * Rendered as a typed 429 so the React layer can tell "you are rate limited"
 * apart from "the model fell over".
 */
final class AiQuotaExceededException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse([
            'error' => 'ai_quota_exceeded',
            'message' => $this->getMessage(),
        ], 429);
    }
}
