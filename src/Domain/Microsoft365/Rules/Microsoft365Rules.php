<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Rules;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

class Microsoft365Rules
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function getMicrosoft365Rules(Customer $customer): array
    {
        $tenantNameRule = new TenantNameRules(
            translator: $this->translator,
            customer: $customer,
        );

        $copilotAmountRule = new CopilotAmountRules(
            translator: $this->translator,
            productRepository: $this->productRepository,
            productSpecRepository: $this->productSpecRepository,
            customer: $customer,
            subscriptionRepository: $this->subscriptionRepository,
        );

        return [
            'subscriptions.microsoft-365' => ['array', $copilotAmountRule],
            'subscriptions.microsoft-365.*.tenant_name' => [$tenantNameRule],
            'subscriptions.microsoft-365.*.tenant_id' => ['nullable', 'uuid'],
        ];
    }
}
