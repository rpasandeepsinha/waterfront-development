<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Waterfront\Domain\Ssl\Enums\Certificate;
use Waterfront\Domain\Ssl\Storage\CertificateCloud;

class CertificateManager
{
    public function __construct(
        private readonly CertificateCloud $cloudDisk
    ) {
    }

    public function saveRootCertificate(string $domain, string $certificate): void
    {
        $this->cloudDisk->storeCertificate($domain, $certificate, Certificate::ROOT);
    }

    public function getRootCertificate(string $domain): string|null
    {
        return $this->cloudDisk->getCertificate($domain, Certificate::ROOT);
    }

    public function saveIntermediateCertificate(string $domain, string $certificate): void
    {
        $this->cloudDisk->storeCertificate($domain, $certificate, Certificate::INTERMEDIATE);
    }

    public function getIntermediateCertificate(string $domain): string|null
    {
        return $this->cloudDisk->getCertificate($domain, Certificate::INTERMEDIATE);
    }

    public function saveMainCertificate(string $domain, string $certificate): void
    {
        $this->cloudDisk->storeCertificate($domain, $certificate, Certificate::MAIN);
    }

    public function getMainCertificate(string $domain): string|null
    {
        return $this->cloudDisk->getCertificate($domain, Certificate::MAIN);
    }
}
