<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class ProductChangeIsAllowed extends AbstractValidator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ProductRepository $productRepository,
        private readonly ProductAllowedChangeRepository $productAllowedChangeRepository,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $subscriptionLines = array_filter($value, fn ($product) => is_array($product) && array_key_exists('subscription_uuid', $product));

        foreach ($subscriptionLines as $line) {
            if (! is_string($line['subscription_uuid'])) {
                return false;
            }

            $subscription = $this->subscriptionRepository->getByUuid($line['subscription_uuid']);

            if ($subscription === null) {
                return false;
            }

            if ($subscription->product->slug !== $line['slug']) {
                $slug = $line['slug'] ?? null;
                assert(is_string($slug));

                $isUpgradeAllowed   = $this->productAllowedChangeRepository->isProductChangeAllowed(ProductChangeType::UPGRADE, $subscription->product, $this->productRepository->findProductBySlug($slug));
                $isDowngradeAllowed = $this->productAllowedChangeRepository->isProductChangeAllowed(ProductChangeType::DOWNGRADE, $subscription->product, $this->productRepository->findProductBySlug($slug));

                if (! $isDowngradeAllowed && ! $isUpgradeAllowed) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.product_change_not_allowed');
    }
}
