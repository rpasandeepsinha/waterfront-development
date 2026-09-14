<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Exceptions;

use Exception;

class ResellerHostingNameserverCoupleException extends Exception
{
    public function __construct(
        private readonly string $domain,
        int $code = 0,
        ?Exception $exception = null,
    ) {
        parent::__construct(
            sprintf(
                'There was an error setting the nameserver for domain %s',
                $domain,
            ),
            $code,
            $exception,
        );
    }

    public function getDomain(): string
    {
        return $this->domain;
    }
}
