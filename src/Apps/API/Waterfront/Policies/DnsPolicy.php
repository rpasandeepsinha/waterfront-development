<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\AuthenticationManager;

class DnsPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository
    ) {
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanManageDns(Subscription $subscription): void
    {
        if (! $this->subscriptionHasActiveDnsSubscription($subscription)) {
            throw new AuthorizationException();
        }

        if ($this->authenticationManager->getAuthenticatedCustomer()->customer->id !== $subscription->customer_id) {
            throw new AuthorizationException();
        }
    }

    public function assertCanEditDnsRecords(Product $product): void
    {
        if (! $this->dnsProductSpecRepository->allowDnsRecordEditing($product)) {
            throw new AuthorizationException();
        }
    }

    public function subscriptionHasActiveDnsSubscription(Subscription $subscription): bool
    {
        return Subscription::query()
            ->where(
                [
                    'customer_id' => $subscription->customer_id,
                    'domain' => $subscription->domain,
                ]
            )
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->whereProductGroupType(ProductGroupType::DNS)
            ->exists();
    }
}
