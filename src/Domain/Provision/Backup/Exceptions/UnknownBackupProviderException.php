<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Exceptions;

use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;

class UnknownBackupProviderException extends BackupException
{
    public function __construct(
        ProvisionProvider $provider,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: sprintf("Can't resolve backup service from unknown provider [%s]", $provider->value),
            previous: $previous,
        );
    }
}
