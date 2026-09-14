<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Factories;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\Domains\Interfaces\DomainDriverInterface;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Domains\Models\OpenproviderProviderCredentials;
use Waterfront\Domain\Domains\Models\RtrProviderCredentials;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Services\OpenproviderService;
use Waterfront\Domain\Placeholder\Services\DomainPlaceholderService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\OpenproviderClient\Factories\OpenproviderClientFactory;
use Waterfront\Infra\RtrClient\Factories\RtrClientFactory;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class DomainServiceFactory
{
    public function __construct(
        private readonly RtrService $rtrService,
        private readonly RtrClientFactory $rtrClientFactory,
        private readonly OpenproviderService $openProviderService,
        private readonly OpenproviderClientFactory $openproviderClientFactory,
        private readonly DomainPlaceholderService $placeholderService,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function driver(
        ProviderSlug $providerSlug,
        ?DomainProviderBusinessUnit $businessUnit = null,
    ): DomainDriverInterface {
        return match ($providerSlug) {
            ProviderSlug::REALTIME_REGISTER => $this->getRtrService($businessUnit),
            ProviderSlug::OPEN_PROVIDER => $this->getOpenproviderService($businessUnit),
            ProviderSlug::PLACEHOLDER => $this->placeholderService,
            // Should never happen, if it does blow up everything.
            ProviderSlug::BASEKIT,
            ProviderSlug::ACRONIS,
            ProviderSlug::XOLPHIN,
            ProviderSlug::DIRECTADMIN,
            ProviderSlug::PLESK,
                => throw new RuntimeException(),
        };
    }

    public function defaultDriver(): DomainDriverInterface
    {
        return $this->driver($this->getDefaultProvider()->slug);
    }

    public function resolveProviderByProduct(Product $product): Provider
    {
        $providerProductSpec = ProductSpec::query()
            ->where('name', 'domain.provider_id')
            ->whereProduct($product)
            ->first();

        if ($providerProductSpec instanceof ProductSpec) {
            return Provider::where('id', $providerProductSpec->value)->firstOrFail();
        }

        return $this->getDefaultProvider();
    }

    public function getDefaultProvider(): Provider
    {
        return Provider::where('default', true)->where('type', ProviderType::DOMAIN)->firstOrFail();
    }

    private function getRtrService(?DomainProviderBusinessUnit $businessUnit): RtrService
    {
        /**
         * If no business unit is set for a DomainDeployment with RTR as provider
         * we will use the default credentials set in the config filled by the
         * environment variables. These are given in the RtrServiceProvider.
         */
        if ($businessUnit === null) {
            $rtrClient = $this->rtrClientFactory->create();

            return $this->rtrService->setHandle(null)->setClient($rtrClient);
        }

        $this->logger->debug(
            sprintf('Updating RealtimeRegister client to domain provider business unit "%s"', $businessUnit->slug),
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                LoggingContextKeys::META => [
                    'business_unit' => $businessUnit,
                ],
            ],
        );

        $credentials = $this->domainDeploymentRepository->getDomainProviderCredentials(
            ProviderSlug::REALTIME_REGISTER,
            $businessUnit,
        );

        // Assert that credentials are RTR and are set
        Assert::isInstanceOf($credentials, RtrProviderCredentials::class);

        $rtrClient = $this->rtrClientFactory->create($credentials);

        return $this->rtrService->setHandle($credentials->handle)->setClient($rtrClient);
    }

    private function getOpenproviderService(?DomainProviderBusinessUnit $businessUnit): OpenproviderService
    {
        /**
         * If no business unit is set for a DomainDeployment with OpenProvider as provider
         * we will use the default credentials set in the config filled by the
         * environment variables. These are set in the OpenproviderClientProvider.
         */
        if ($businessUnit === null) {
            $client = $this->openproviderClientFactory->create();

            return $this->openProviderService->setClient($client);
        }

        $this->logger->debug(
            sprintf('Updating OpenProvider client to domain provider business unit "%s"', $businessUnit->slug),
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::OPENPROVIDER,
                LoggingContextKeys::META => [
                    'business_unit' => $businessUnit,
                ],
            ],
        );

        $credentials = $this->domainDeploymentRepository->getDomainProviderCredentials(
            ProviderSlug::OPEN_PROVIDER,
            $businessUnit,
        );

        Assert::isInstanceOf($credentials, OpenproviderProviderCredentials::class);

        $client = $this->openproviderClientFactory->create($credentials);

        return $this->openProviderService->setClient($client);
    }
}
