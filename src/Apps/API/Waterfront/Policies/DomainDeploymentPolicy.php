<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\AuthenticationManager;

class DomainDeploymentPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
        private readonly ProductSpecRepository $productSpecRepository
    ) {
    }

    /** @return string[] */
    public function getAvailableActions(DomainDeployment $deployment): array
    {
        $actions = [];

        try {
            self::assertCanShowCoupleFreeHostingPackage();
            $actions[] = 'showCoupleFreeHostingPackage';
        } catch (AuthenticationException|AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanUseDnsSec($deployment);
            $actions[] = 'dnssecEnabledForTLD';
        } catch (AuthorizationException) {
            // @ignoreException
        }

        return $actions;
    }

    public function assertCanUseDnsSec(DomainDeployment $domainDeployment): void
    {
        $product = $domainDeployment->subscription->product;

        $specValue = $this->productSpecRepository->getStringValueOfSpecification($product, ProductSpecName::DOMAIN_DNSSEC_ENABLED);
        if (! in_array($specValue, ['yes', '1'], true)) {
            throw new AuthorizationException('Dnssec is disabled for this product');
        }
    }

    private function assertCanShowCoupleFreeHostingPackage(): void
    {
        $customer  = $this->authManager->getAuthenticatedCustomer()->customer;

        $customer->loadMissing('subscriptions.product.productGroup');

        $list = $customer->subscriptions->filter(
            fn (Subscription $s) =>
            ! in_array($s->administrative_status, [AdministrativeStatus::EXPIRED->value, AdministrativeStatus::ARCHIVED->value], true)
            && $s->product->productGroup->slug === ProductGroupType::HOSTING
            && ! $s->product->isMailOnlyServer()
            && ! $s->product->isSitebuilderProduct()
        );

        if ($list->isEmpty()) {
            throw new AuthorizationException();
        }
    }
}
