<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;

class StoreProvisionResultException extends ProvisionException
{
    public function __construct(
        ProvisionResultInterface $result,
        int $originRequestId,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf(
                'Unable to store provision result in the database from request [%d]. Provision Status: %s',
                $originRequestId,
                $result->provisionStatus->value,
            ),
            code: $code,
            previous: $previous,
        );
    }
}
