<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Webmozart\Assert\Assert;

class CertificateRetriever
{
    public function __construct(
        private readonly CertificateManager $certificateManager,
        private readonly CsrManager $csrManager,
    ) {
    }

    /**
     * Retrieves the content of a SSL certificate (*.key or *.crt) file.
     *
     * @throws FileNotFoundException
     */
    public function getCertificate(SslDeployment $sslDeployment, string $type): string
    {
        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $certificate = $this->retrieveCertificate($domain, $type);

        Assert::stringNotEmpty(
            $certificate,
            sprintf(
                'Certificate for %s of type %s not found',
                $domain,
                $type
            )
        );

        return $certificate;
    }

    /**
     * Gets a list of certificate files that are available for download.
     *
     * @throws FileNotFoundException
     *
     * @return array<string,string>
     */
    public function getAvailableCertificateTypes(SslDeployment $sslDeployment): array
    {
        $domain = $sslDeployment->subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $types = [];

        if ($this->retrieveCertificate($domain, 'csr') !== null) {
            $types['CSR'] = 'csr';
        }

        if ($this->retrieveCertificate($domain, 'key') !== null) {
            $types['Private key'] = 'key';
        }

        if ($this->retrieveCertificate($domain, 'crt') !== null) {
            $types['Certificate'] = 'crt';
            $types['Root'] = 'root';
            $types['Intermediate'] = 'intermediate';
        }

        return $types;
    }

    /**
     * @throws FileNotFoundException
     */
    private function retrieveCertificate(string $domain, string $type): ?string
    {
        $fallbackDomain = $this->getFallbackDomainName($domain);

        if ($type === 'csr') {
            return $this->getCsr($domain, $fallbackDomain);
        }

        if ($type === 'key') {
            return $this->getPrivateKey($domain, $fallbackDomain);
        }

        if ($type === 'root') {
            return $this->getRootCertificate($domain, $fallbackDomain);
        }

        if ($type === 'intermediate') {
            return $this->getIntermediateCertificate($domain, $fallbackDomain);
        }

        if ($type === 'crt') {
            return $this->getMainCertificate($domain, $fallbackDomain);
        }

        return null;
    }

    /**
     * Historically we stored the certificate files with a `*.` prefix for wildcard certificates.
     * But since the RTR implementation we stopped doing this.
     * To stay backward compatible this fallback domain is required, as the certificate files can be stored in
     * either way.
     * See https://yh-jira.atlassian.net/browse/WATER-4711.
     */
    private function getFallbackDomainName(string $domain): string
    {
        return str_starts_with($domain, '*.') ? substr($domain, 2) : '*.' . $domain;
    }

    /**
     * @throws FileNotFoundException
     */
    private function getCsr(string $domain, string $fallbackDomain): ?string
    {
        if ($this->csrManager->hasCsr($domain)) {
            return $this->csrManager->getRawCsr($domain);
        }

        if ($this->csrManager->hasCsr($fallbackDomain)) {
            return $this->csrManager->getRawCsr($fallbackDomain);
        }

        return null;
    }

    /**
     * @throws FileNotFoundException
     */
    private function getPrivateKey(string $domain, string $fallbackDomain): ?string
    {
        if ($this->csrManager->hasPrivateKey($domain)) {
            return $this->csrManager->getPrivateKey($domain);
        }

        if ($this->csrManager->hasPrivateKey($fallbackDomain)) {
            return $this->csrManager->getPrivateKey($fallbackDomain);
        }

        return null;
    }

    private function getRootCertificate(string $domain, string $fallbackDomain): ?string
    {
        $cert = $this->certificateManager->getRootCertificate($domain);
        if ($cert !== null && $cert !== '') {
            return $cert;
        }

        $cert = $this->certificateManager->getRootCertificate($fallbackDomain);
        if ($cert !== null && $cert !== '') {
            return $cert;
        }

        return null;
    }

    private function getIntermediateCertificate(string $domain, string $fallbackDomain): ?string
    {
        $cert = $this->certificateManager->getIntermediateCertificate($domain);
        if ($cert !== null && $cert !== '') {
            return $cert;
        }

        $cert = $this->certificateManager->getIntermediateCertificate($fallbackDomain);
        if ($cert !== null && $cert !== '') {
            return $cert;
        }

        return null;
    }

    private function getMainCertificate(string $domain, string $fallbackDomain): ?string
    {
        $cert = $this->certificateManager->getMainCertificate($domain);
        if ($cert !== null && $cert !== '') {
            return $cert;
        }

        $cert = $this->certificateManager->getMainCertificate($fallbackDomain);
        if ($cert !== null && $cert !== '') {
            return $cert;
        }

        return null;
    }
}
