<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\Exceptions;

use Exception;
use Throwable;

abstract class MollieApiException extends Exception
{
    public function __construct(
        public readonly int $status,
        public readonly string $title,
        public readonly string $detail,
        public readonly string|null $field,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            sprintf(
                'Mollie API exception, code: %d, title: %s, detail: %s, field: %s',
                $status,
                $title,
                $detail,
                $field
            ),
            $this->status,
            $previous
        );
    }
}
