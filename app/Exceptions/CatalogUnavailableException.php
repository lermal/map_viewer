<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The catalog file is missing or broken and no earlier version is available.
 * Shown as "temporarily unavailable" so visitors and crawlers retry later.
 */
class CatalogUnavailableException extends HttpException
{
    public function __construct(string $jsonPath)
    {
        parent::__construct(503, "Catalog data is not available: {$jsonPath}", null, ['Retry-After' => '300']);
    }
}
