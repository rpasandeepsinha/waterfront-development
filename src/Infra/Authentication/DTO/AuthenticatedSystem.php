<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication\DTO;

use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;

class AuthenticatedSystem
{
    public function __construct(public readonly KratosIdentity $identitySchema)
    {
    }
}
