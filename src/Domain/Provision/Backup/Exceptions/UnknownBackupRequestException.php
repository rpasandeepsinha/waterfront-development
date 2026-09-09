<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;

class UnknownBackupRequestException extends BackupException
{
    public function __construct(
        ProvisionRequestInterface $request,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf('No implementation found in backup service for request [%s]', $request::class),
            previous: $previous,
        );
    }
}
