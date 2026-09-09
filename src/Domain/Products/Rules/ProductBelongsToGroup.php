<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Rules;

use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class ProductBelongsToGroup extends AbstractValidator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly ProductGroupType $productGroup,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        return is_string($value) && $this->productRepository->productExistsForGroup($value, $this->productGroup);
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.product_not_in_group');
    }
}
