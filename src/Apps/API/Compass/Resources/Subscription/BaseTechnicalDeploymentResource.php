<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Subscription;

use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Webmozart\Assert\Assert;

readonly class BaseTechnicalDeploymentResource
{
    public function __construct(
        private SubscriptionPolicy $subscriptionPolicy,
    ) {
    }

    /**
     * @return array{
     *     id: int,
     *     administrative_subscription_uuid: string,
     *     technical_status: string|null,
     *     domain: string|null,
     *     provider: string|null,
     *     available_actions: array<string>,
     *     last_result: string,
     * }
     */
    public function toArray(
        DomainDeployment|HostingDeployment|ResellerHostingDeployment|VirtualMachineDeployment|SslDeployment $technicalDeployment,
    ): array {
        return [
            'id' => $technicalDeployment->id,
            'administrative_subscription_uuid' => $technicalDeployment->subscription->uuid,
            'technical_status' => $technicalDeployment->subscription->technical_status,
            'domain' => $technicalDeployment->subscription->domain ?? null,
            'provider' => $this->getServiceProvider($technicalDeployment->subscription, $technicalDeployment),
            'available_actions' => $this->subscriptionPolicy->getAvailableActions($technicalDeployment->subscription),
            'last_result' => '',
        ];
    }

    private function getServiceProvider(
        Subscription $subscription,
        HostingDeployment|DomainDeployment|ResellerHostingDeployment|VirtualMachineDeployment|SslDeployment $technicalDeployment,
    ): ?string {
        return match ($subscription->product->productGroup->slug) {
            ProductGroupType::VPS => ProvisionProvider::CLOUDSTACK->value,
            ProductGroupType::RESELLER_HOSTING => $subscription->resellerHostingDeployment?->provider->slug->value,
            ProductGroupType::HOSTING => $this->determineActualHostingProvider($subscription, $technicalDeployment),
            ProductGroupType::EXTENSION => $subscription->domainDeployment->provider->slug->value ?? null,
            ProductGroupType::SSL => $subscription->sslDeployment?->provider->slug->value,
            ProductGroupType::MANUAL_SUBSCRIPTION => null,
            default => null,
        };
    }

    private function determineActualHostingProvider(
        Subscription $subscription,
        HostingDeployment|DomainDeployment|ResellerHostingDeployment|VirtualMachineDeployment|SslDeployment $technicalDeployment,
    ): ?string {
        $hostingDeployment = $technicalDeployment;
        Assert::isInstanceOf(
            $hostingDeployment,
            HostingDeployment::class,
            'Deployment is not instance of HostingDeployment',
        );

        return match (true) {
            $subscription->product->isSitebuilderProduct() => $hostingDeployment->sitebuilderProvider?->slug->value,
            $subscription->product->isMailOnlyServer() => $hostingDeployment->mailProvider?->slug->value,
            default => $hostingDeployment->provider?->slug->value,
        };
    }
}
