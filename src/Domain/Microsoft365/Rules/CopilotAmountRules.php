<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Rules;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class CopilotAmountRules extends AbstractValidator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly Customer $customer,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $microsoft365ProductCount = $this->subscriptionRepository->getSubscriptionsWhereProductSlugAndCustomerDoesNotMatchCount($this->customer, ProductSlug::MICROSOFT_COPILOT->value);
        $copilotCount = $this->subscriptionRepository->getSubscriptionsWhereProductSlugAndCustomerMatchCount($this->customer, ProductSlug::MICROSOFT_COPILOT->value);

        /** @var array<string, mixed> $subscription */
        foreach ($value as $subscription) {
            if (! array_key_exists('slug', $subscription)) {
                return false;
            }

            /** @var string $slug */
            $slug = $subscription['slug'];

            if ($slug === ProductSlug::MICROSOFT_COPILOT->value) {
                $copilotCount++;
                continue;
            }

            $product = $this->productRepository->findProductBySlug($slug);

            $productSpec = $this->productSpecRepository->booleanSpecificationIsTrue($product, ProductSpecName::MICROSOFT365_ALLOW_COPILOT);

            if (! $productSpec) {
                continue;
            }

            $microsoft365ProductCount++;
        }

        if ($copilotCount > $microsoft365ProductCount) {
            return false;
        }

        return true;
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.m365.more-copilot-then-products');
    }
}
