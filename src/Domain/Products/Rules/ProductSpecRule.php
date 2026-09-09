<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Rules;

use Illuminate\Database\Eloquent\Builder;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

/**
 * Rule for validating that the product spec for the domain name has a specific value.
 */
class ProductSpecRule extends AbstractValidator
{
    /**
     * @param string[] $values
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductGroupType $productGroup,
        private readonly string $productSpecName,
        private readonly array $values,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        return (bool) Subscription::query()->whereProductGroupType($this->productGroup)
            ->where('domain', $value)
            ->whereHas('product.productSpecs', function (Builder $query): void {
                $query->where('name', $this->productSpecName)
                    ->whereIn('value', $this->values);
            })
            ->count();
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.productspec');
    }
}
