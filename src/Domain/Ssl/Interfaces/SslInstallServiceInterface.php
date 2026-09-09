<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces;

interface SslInstallServiceInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @see RemoteSslService::prepareCertificateInstallParameters()
     */
    public function installCertificate(array $data): string;
}
