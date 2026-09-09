<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces;

use Waterfront\Domain\Hosting\Interfaces\Hosting\ClientInterface;
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters;

interface InstallInterface extends ClientInterface
{
    public function installCertificate(Parameters $parameters): string;
}
