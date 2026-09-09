<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Exceptions;

use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

class UnknownMicrosoft365RequestException extends Microsoft365Exception
{
    public function __construct(ProvisionRequestInterface $request)
    {
        $message = sprintf('No implementation found in Microsoft365 service for request [%s]', $request::class);
        parent::__construct(
            $message
        );
    }
}
