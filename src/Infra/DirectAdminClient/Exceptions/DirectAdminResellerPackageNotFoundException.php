<?php

declare(strict_types=1);

namespace Waterfront\Infra\DirectAdminClient\Exceptions;

class DirectAdminResellerPackageNotFoundException extends DirectAdminException
{
    public function __construct(string $resellerPackage)
    {
        parent::__construct("Reseller Package {$resellerPackage} doesn't exist on the server.");
    }
}
