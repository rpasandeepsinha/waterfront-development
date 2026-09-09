<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Exceptions;

class DomainNotPromotedException extends Microsoft365Exception
{
    public function __construct(string $domain)
    {
        parent::__construct(message: sprintf('Unable to promote domain [%s]', $domain));
    }
}
