<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

class StoreProvisionRequestException extends ProvisionException
{
    public function __construct(ProvisionRequestInterface $request, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            message: sprintf(
                'Unable to store provision request [%s] in the database. Context: [%s] Tag: [%s]',
                $request->name->value,
                $request instanceof ProvisionContextRequestInterface ? $request->context->toString() : 'null',
                $request->tag->toString(),
            ),
            code: $code,
            previous: $previous
        );
    }
}
