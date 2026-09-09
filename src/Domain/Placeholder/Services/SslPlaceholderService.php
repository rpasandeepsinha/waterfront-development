<?php

declare(strict_types=1);

namespace Waterfront\Domain\Placeholder\Services;

use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotFoundException;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Interfaces\SslDriverInterface;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\DTO\DcvDetails;
use Waterfront\Support\Exceptions\NotImplementedException;

class SslPlaceholderService extends PlaceHolderService implements SslDriverInterface
{
    /**
     * @throws DriverNotFoundException
     */
    public function create(
        ProductSpec $productSpec,
        int $period,
        array $customerData,
        SslDeployment $sslDeployment,
        ?string $csr = null
    ): Result {
        $provider = Provider::where('type', ProviderType::SSL)->where('slug', ProviderSlug::PLACEHOLDER)->first();

        if ($provider === null) {
            throw new DriverNotFoundException(sprintf(
                'There was no sslprovider found with the slug %s for the SSL deployment with id: %s',
                ProviderSlug::PLACEHOLDER->value,
                $sslDeployment->id
            ));
        }

        $sslDeployment->update(['provider_id' => $provider->id]);

        $subscription = $sslDeployment->subscription;

        $provisionDetail = $this->getProvisionDetailFromSubscription($subscription);
        $this->notificationService->sendCreationNotification($provisionDetail);

        return Result::create(['status' => TechnicalStatus::PENDING->value]);
    }

    /**
     * Retrieve certificate info from the provider.
     */
    public function retrieve(SslDeployment $sslDeployment): Result
    {
        throw new NotImplementedException();
    }

    public function reissue(
        array $customerData,
        SslDeployment $sslDeployment,
        string $csr
    ): Result {
        throw new NotImplementedException();
    }

    public function renew(SslDeployment $sslDeployment): Result
    {
        throw new NotImplementedException();
    }

    /**
     * Check if the domain has a valid certificate.
     */
    public function check(string $domain = '', int $period = 12): bool
    {
        throw new NotImplementedException();
    }

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
        bool $alreadySaved = false
    ): array {
        throw new NotImplementedException();
    }

    /**
     * Save certificates to storage.
     *
     * @param array<string> $certificates
     */
    public function prepareCertificates(int $certificateId, string $domain, array $certificates): void
    {
        throw new NotImplementedException();
    }

    /**
     * Checks if we can find the private CSR for a domain.
     */
    public function csrExistsForDomain(string $domain): bool
    {
        throw new NotImplementedException();
    }

    /**
     * Validate CSR.
     */
    public function validate(string $csr, string $domain, ?SslDeployment $sslDeployment): never
    {
        throw new NotImplementedException();
    }

    /**
     * Check if private key matches certificate.
     */
    public function checkPrivateKeyMatches(string $domain): bool
    {
        throw new NotImplementedException();
    }

    /**
     * Resolve CSR domain.
     */
    public function resolveCsrDomain(string $commonName): string
    {
        throw new NotImplementedException();
    }

    /**
     * Compare two domains.
     */
    public function compareTwoDomains(string $one, string $two): bool
    {
        throw new NotImplementedException();
    }

    public function resendDcv(SslDeployment $sslDeployment): Result
    {
        throw new NotImplementedException();
    }

    public function getSslCnameRecord(SslDeployment $sslDeployment): ?DcvDetails
    {
        throw new NotImplementedException();
    }

    public function hasSslRequest(string $domain): bool
    {
        throw new NotImplementedException();
    }
}
