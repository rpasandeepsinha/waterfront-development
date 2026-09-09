<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Factories;

use Illuminate\Support\Facades\Log;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class PlaceHolderDeploymentFactory
{
    public function __construct(
        private readonly ProviderRepository $providerRepository,
        private readonly SitebuilderService $sitebuilderService,
    ) {
    }

    public function checkIfDomainPlaceholderProvidersExist(): void
    {
        $this->providerRepository->getByType(ProviderType::DOMAIN, ProviderSlug::PLACEHOLDER);
    }

    public function checkIfHostingPlaceholderProvidersExist(): void
    {
        $this->providerRepository->getByType(ProviderType::HOSTING, ProviderSlug::PLACEHOLDER);
    }

    public function checkIfMailOnlyPlaceholderProvidersExist(): void
    {
        $this->providerRepository->getByType(ProviderType::MAILONLY, ProviderSlug::PLACEHOLDER);
    }

    public function checkIfSitebuilderPlaceholderProvidersExist(): void
    {
        $this->providerRepository->getByType(ProviderType::MAILONLY, ProviderSlug::PLACEHOLDER);
        $this->providerRepository->getByType(ProviderType::SITEBUILDER, ProviderSlug::PLACEHOLDER);
    }

    public function checkIfSslPlaceholderProvidersExist(): void
    {
        $this->providerRepository->getByType(ProviderType::SSL, ProviderSlug::PLACEHOLDER);
    }

    /**
     * Takes a given migration payload and the created subscription
     * and builds up a placeholder deployment. That can be used to perform a technical migration
     * at a later point in time. This is only for setting the needed data in the future so that technical migration won't have to
     * initially insert data into the database.
     *
     * The placeholders will be coupled to the placeholder provider based on a product group if that is applicable for
     * the referenced product group typing. FE. domains have a deployment but DNS does not!
     */
    public function create(Subscription $subscription, ImplementableProducts $implementableProduct): void
    {
        $productGroupSlug = $subscription->product->productGroup->slug;

        match ($productGroupSlug) {
            ProductGroupType::HOSTING => $this->createHostingPlaceHolder($subscription, $implementableProduct),
            ProductGroupType::RESELLER_HOSTING => $this->createResellerHostingPlaceHolder($subscription),
            ProductGroupType::SSL => $this->createSslPlaceHolder($subscription),
            ProductGroupType::EXTENSION => $this->createDomainPlaceHolder($subscription),
            default => $this->handleDefault($productGroupSlug),
        };
    }

    private function createDomainPlaceHolder(Subscription $subscription): DomainDeployment
    {
        $provider = $this->providerRepository->getByType(ProviderType::DOMAIN, ProviderSlug::PLACEHOLDER);

        $domainDeployment = new DomainDeployment();
        $domainDeployment->provider()->associate($provider);
        $domainDeployment->subscription()->associate($subscription);
        $domainDeployment->save();

        return $domainDeployment;
    }

    private function createSslPlaceHolder(Subscription $subscription): SslDeployment
    {
        $provider = $this->providerRepository->getByType(ProviderType::SSL, ProviderSlug::PLACEHOLDER);

        $sslDeployment = new SslDeployment();
        $sslDeployment->provider()->associate($provider);
        $sslDeployment->subscription()->associate($subscription);
        $sslDeployment->save();

        return $sslDeployment;
    }

    private function createHostingPlaceHolder(Subscription $subscription, ImplementableProducts $implementableProduct): void
    {
        if ($subscription->product->isRedirectProduct()) {
            return;
        }

        if ($subscription->product->isSitebuilderProduct()) {
            $mailProvider = $this->providerRepository->getByType(ProviderType::MAILONLY, ProviderSlug::PLACEHOLDER);

            $hostingDeployment = new HostingDeployment();
            $hostingDeployment->mailProvider()->associate($mailProvider);

            $email = $subscription->customer->email;
            if (! $this->sitebuilderService->hasSitebuilderThroughGateway($email)) {
                $sitebuilderProvider = $this->providerRepository->getByType(
                    ProviderType::SITEBUILDER,
                    ProviderSlug::PLACEHOLDER
                );
                $hostingDeployment->sitebuilderProvider()->associate($sitebuilderProvider);
            }

            $hostingDeployment->subscription()->associate($subscription);
            $hostingDeployment->saveQuietly();

            return;
        }

        if ($implementableProduct === ImplementableProducts::MAIL_ONLY) {
            $mailProvider = $this->providerRepository->getByType(ProviderType::MAILONLY, ProviderSlug::PLACEHOLDER);

            $hostingDeployment = new HostingDeployment();
            $hostingDeployment->mailProvider()->associate($mailProvider);
            $hostingDeployment->subscription()->associate($subscription);
            $hostingDeployment->saveQuietly();

            return;
        }

        $provider = $this->providerRepository->getByType(ProviderType::HOSTING, ProviderSlug::PLACEHOLDER);

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->provider()->associate($provider);
        $hostingDeployment->subscription()->associate($subscription);
        $hostingDeployment->save();
    }

    private function createResellerHostingPlaceHolder(Subscription $subscription): void
    {
        $provider = $this->providerRepository->getByType(ProviderType::HOSTING, ProviderSlug::PLACEHOLDER);

        $resellerHostingDeployment = new ResellerHostingDeployment();
        $resellerHostingDeployment->provider()->associate($provider);
        $resellerHostingDeployment->subscription()->associate($subscription);
        $resellerHostingDeployment->server_id = null;
        $resellerHostingDeployment->save();
    }

    private function handleDefault(ProductGroupType $productGroupSlug): bool
    {
        Log::debug(sprintf(
            'PlaceHolder creation. Given product group slug: %s does not support deployments! no placeholder needed!',
            $productGroupSlug->value
        ));

        return true;
    }
}
