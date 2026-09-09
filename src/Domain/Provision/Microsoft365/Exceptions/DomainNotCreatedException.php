<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Exceptions;

class DomainNotCreatedException extends Microsoft365Exception
{
    public function __construct(string $domain)
    {
        parent::__construct(message: sprintf('Unable to create domain [%s]', $domain));
    }
}
