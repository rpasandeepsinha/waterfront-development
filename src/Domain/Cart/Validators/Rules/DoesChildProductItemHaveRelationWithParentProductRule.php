<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductAddonCouplingRepository;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class DoesChildProductItemHaveRelationWithParentProductRule extends AbstractValidator
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly ProductAddonCouplingRepository $productAddonCouplingRepository,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        $productCollection = $this->getAllRequiredProducts($value);

        foreach ($value as $item) {
            assert(is_array($item));
            if (! array_key_exists('children', $item)) {
                continue;
            }

            $parentProduct = $productCollection->where('slug', $item['slug'])->first();

            if ($parentProduct === null) {
                return false;
            }

            foreach ($item['children'] as $childProductGroup => $children) {
                if ($childProductGroup !== ProductGroupType::ADD_ON->value) {
                    continue;
                }

                foreach ($children as $child) {
                    $childProduct = $productCollection->where('slug', $child['slug'])->first();
                    assert($childProduct instanceof Product);
                    $valid = $this->productAddonCouplingRepository->existsForParentIdAndAddonId(
                        $parentProduct->id,
                        $childProduct->id,
                    );

                    if (! $valid) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.it-fails');
    }

    /**
     * @param array<mixed> $value
     *
     * @return Collection<int, Product>
     */
    private function getAllRequiredProducts(array $value): Collection
    {
        $slugs = [];

        foreach ($value as $item) {
            assert(is_array($item));
            if (! array_key_exists('children', $item)) {
                continue;
            }

            $slugs[] = $item['slug'];

            foreach ($item['children'] as $childProductGroup => $children) {
                if ($childProductGroup !== ProductGroupType::ADD_ON->value) {
                    continue;
                }

                foreach ($children as $child) {
                    $slugs[] = $child['slug'];
                }
            }
        }

        $uniqueSlugs = array_unique($slugs);

        return $this->productRepository->getProductsBySlugs($uniqueSlugs);
    }
}
