<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

class OrderHostingRules
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ProductRepository $productRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param mixed[] $input
     *
     * @return array<string, string|array<string|ValidationRule>>
     */
    public function getHostingRules(array $input): array
    {
        return [
            // When ordering hosting without a domain this field will not be
            // present in the order and the rule will not be applied.
            'subscriptions.hosting.*.domain' => [new ValidDnsPresentForNewCoupledHostingRule(
                $input,
                $this->subscriptionRepository,
                $this->productRepository,
                $this->productSpecRepository,
                $this->translator,
            )],
        ];
    }
}
