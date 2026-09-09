<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces;

use Waterfront\Domain\Hosting\Interfaces\Hosting\ClientInterface;

interface SelectInterface extends ClientInterface
{
    public function selectCertificate(string $domain, string $certificateName): string;
}
