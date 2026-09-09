<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Services;

use Waterfront\Domain\Domains\Models\DomainProviderStatus;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;

readonly class DomainProviderHistory
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function saveHistory(RtrResponseLog $log, ProviderSlug $domainProviderSlug, string $domain, string $status, string $message): void
    {
        $subscription = $this->subscriptionRepository->getSubscriptionByDomainAndGroup($domain, ProductGroupType::EXTENSION);
        $domainDeployment = $subscription->domainDeployment;

        DomainProviderStatus::create([
            'rtr_response_log_id' => $log->id,
            'provider_id' => $this->getDomainProvider($domainProviderSlug)->id,
            'domain_deployment_id' => $domainDeployment?->id,
            'status' => $status,
            'received_result' => $message,
        ]);
    }

    private function getDomainProvider(ProviderSlug $domainProviderSlug): Provider
    {
        return Provider::where('slug', $domainProviderSlug)->where('type', ProviderType::DOMAIN)->firstOrFail();
    }
}
