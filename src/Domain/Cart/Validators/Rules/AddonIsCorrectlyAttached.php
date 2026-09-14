<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductAddonCouplingRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class AddonIsCorrectlyAttached extends AbstractValidator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductAddonCouplingRepository $productAddonCouplingRepository,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $addonProducts = array_filter(
            $value,
            fn ($product) => is_array($product) && array_key_exists('parent_subscription_uuid', $product),
        );
        $existingSubscriptions = array_column($addonProducts, 'parent_subscription_uuid');
        $existingSubscriptions = Subscription::whereIn('uuid', $existingSubscriptions)->with('product')->get();
        $orderedProducts = array_unique(array_column($addonProducts, 'slug'));
        $orderedProducts = Product::whereIn('slug', $orderedProducts)->get();

        return array_all($addonProducts, function ($addonProduct) use ($existingSubscriptions, $orderedProducts) {
            $existingSubscription = $existingSubscriptions
                ->where('uuid', $addonProduct['parent_subscription_uuid'])
                ->firstOrFail();
            $orderedProduct = $orderedProducts->where('slug', $addonProduct['slug'])->firstOrFail();

            return $this->productAddonCouplingRepository->existsForParentIdAndAddonId(
                $existingSubscription->product->id,
                $orderedProduct->id,
            );
        });
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.addon_product_should_be_linked_to_parent');
    }
}
