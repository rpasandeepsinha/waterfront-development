<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Waterfront\Domain\Providers\Enums\ProviderSlug;

class NoCredentialsForDomainBusinessUnitException extends Exception
{
    public function __construct(
        string $businessUnitSlug,
        ProviderSlug $providerSlug,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'The given business unit slug [%s] does not have credentials for the given provider [%s]. Please ensure that the business unit & credentials exists and is correctly configured.',
                $businessUnitSlug,
                $providerSlug->value,
            ),
            $code,
            $previous,
        );
    }
}
