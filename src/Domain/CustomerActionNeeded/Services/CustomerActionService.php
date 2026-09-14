<?php

declare(strict_types=1);

namespace Waterfront\Domain\CustomerActionNeeded\Services;

use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\CustomerActionNeeded\DTO\CustomerActionNeeded;
use Waterfront\Domain\CustomerActionNeeded\Enums\CustomerActionSlug;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Orders\Repositories\OrderRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class CustomerActionService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly DomainService $domainService,
        private readonly Microsoft365CustomerInfoRepository $m365CustomerInfoRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return Collection<int, CustomerActionNeeded>
     */
    public function getCustomerActionsFromOrderUuid(UuidInterface $orderUuid): Collection
    {
        $order = $this->orderRepository->getOrderByUuid($orderUuid);

        if ($order === null) {
            return new Collection();
        }

        $subscriptions = $this->orderRepository->getSubscriptionsByOrderUuid($orderUuid);

        if ($subscriptions->isEmpty()) {
            return new Collection();
        }

        $customerActions = new Collection();

        // Actions for subscriptions such as TLD needs contact verification or m365 needs signing
        foreach ($subscriptions as $subscription) {
            $customerActions = $customerActions->merge($this->getCustomerActionsFromSubscription($subscription));
        }

        if (! $order->customer->is_verified) {
            $customerActions->push(new CustomerActionNeeded(
                title: $this->translator->translate('customer-action.account-verification.title'),
                message: $this->translator->translate('customer-action.account-verification.title', [
                    'email' => $order->customer->email,
                ]),
                slug: CustomerActionSlug::ACCOUNT_VERIFICATION,
            ));
        }

        return $customerActions;
    }

    /**
     * @return Collection<int, CustomerActionNeeded>
     */
    public function getCustomerActionsFromSubscription(Subscription $subscription): Collection
    {
        /* This also returns a collection because a single subscription
         * could have multiple customer actions on it, such as a tld
         * that is a deferred transfer AND needs contact validation.
         */
        return match ($subscription->product->productGroup->slug) {
            ProductGroupType::EXTENSION => $this->domainService->getCustomerActions($subscription->domainDeployment),
            ProductGroupType::BACKUP => $this->getAcronisCustomerAction($subscription->product->slug),
            ProductGroupType::MICROSOFT_365 => $this->getM365CustomerAction($subscription),
            default => new Collection(),
        };
    }

    /**
     * @return Collection<int, CustomerActionNeeded>
     */
    public function getM365CustomerAction(Subscription $subscription): Collection
    {
        $customerInfo = $this->m365CustomerInfoRepository->findByCustomerAndDomain(
            $subscription->customer,
            $subscription->domain,
        );

        if ($customerInfo === null || $customerInfo->mca_signed_at !== null) {
            return new Collection();
        }

        return new Collection()->push(new CustomerActionNeeded(
            title: $this->translator->translate('customer-action.m365-license.title'),
            message: $this->translator->translate('customer-action.m365-license'),
            slug: CustomerActionSlug::M365_MCA_SIGNED,
            productGroupSlug: ProductGroupType::MICROSOFT_365,
            productSlug: $subscription->product->slug,
        ));
    }

    /**
     * @return Collection<int, CustomerActionNeeded>
     */
    private function getAcronisCustomerAction(string $productSlug): Collection
    {
        return new Collection()->push(
            new CustomerActionNeeded(
                title: $this->translator->translate('customer-action.acronis.title'),
                message: $this->translator->translate('customer-action.acronis'),
                slug: CustomerActionSlug::ACRONIS_BACKUP,
                productGroupSlug: ProductGroupType::BACKUP,
                productSlug: $productSlug,
            ),
        );
    }
}
