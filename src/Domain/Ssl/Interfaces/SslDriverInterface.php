<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces;

use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;

interface SslDriverInterface
{
    /**
     * @param array<string, mixed> $customerData
     */
    public function create(
        ProductSpec $productSpec,
        int $period,
        array $customerData,
        SslDeployment $sslDeployment,
        ?string $csr = null,
    ): Result;

    /** @param array<string, mixed> $customerData */
    public function reissue(
        array $customerData,
        SslDeployment $sslDeployment,
        string $csr,
    ): Result;

    public function renew(SslDeployment $sslDeployment): Result;

    public function check(string $domain = '', int $period = 12): bool;

    public function retrieve(SslDeployment $sslDeployment): Result;

    /**
     * Save certificates to storage.
     *
     * @param array<string> $certificates
     */
    public function prepareCertificates(int $certificateId, string $domain, array $certificates): void;

    /**
     * Collect the required parameters for installing a certificate.
     *
     * @param array<string> $certificates
     *
     * @return array<mixed>
     */
    public function prepareCertificateInstallParameters(
        int $certificateId,
        string $domain,
        array $certificates,
        bool $alreadySaved = false,
    ): array;

    public function csrExistsForDomain(string $domain): bool;

    /**
     * @return string[]|bool
     */
    public function validate(string $csr, string $domain, ?SslDeployment $sslDeployment): array|bool;

    public function resolveCsrDomain(string $commonName): string;

    public function compareTwoDomains(string $one, string $two): bool;

    public function checkPrivateKeyMatches(string $domain): bool;

    public function resendDcv(SslDeployment $sslDeployment): Result;

    public function getSslCnameRecord(SslDeployment $sslDeployment): ?DcvDetails;

    public function hasSslRequest(string $domain): bool;
}
