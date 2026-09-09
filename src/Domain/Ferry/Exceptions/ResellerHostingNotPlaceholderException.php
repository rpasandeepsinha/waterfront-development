<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Throwable;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;

class ResellerHostingNotPlaceholderException extends Exception
{
    public function __construct(ResellerHostingDeployment $resellerHostingDeployment, ?string $providerSlug = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Reseller hosting subscription with id %d is not placeholder, but %s',
                $resellerHostingDeployment->id,
                $providerSlug ?? '',
            ),
            $code,
            $previous
        );
    }
}
