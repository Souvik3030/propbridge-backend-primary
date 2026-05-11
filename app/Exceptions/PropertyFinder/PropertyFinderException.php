<?php

declare(strict_types=1);

namespace App\Exceptions\PropertyFinder;

use Exception;
use Throwable;
use Illuminate\Http\JsonResponse;

class PropertyFinderException extends Exception
{
    public function __construct(
        string $message,
        private int $statusCode = 500,
        ?Throwable $previous = null,
        private array $context = []
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'context' => $this->context,
        ], $this->statusCode);
    }
}
