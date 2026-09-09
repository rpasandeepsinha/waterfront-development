<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Products\Enums\ProductGroupType;

readonly class CreateProductDTO
{
    /**
     * @param Specifications[]|null                    $specifications
     * @param AllowedChange[]|null                     $allowedChange
     * @param Promotion[]|null                         $promotions
     * @param ProductPriceEntryDTO[]|null              $productPrices
     * @param ProductAddonDTO[]|null                   $addons
     * @param IntroductionPriceConfigurationDTO[]|null $introductionPriceConfiguration
     */
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        #[SerializedName('groupSlug')]
        public ProductGroupType $groupSlug,
        #[SerializedName('shopConfig')]
        public ShopConfig $shopConfig,
        #[SerializedName('specifications')]
        public ?array $specifications,
        #[SerializedName('allowedChange')]
        public ?array $allowedChange,
        #[SerializedName('promotions')]
        public ?array $promotions,
        #[SerializedName('prices')]
        public ?array $productPrices,
        #[SerializedName('addons')]
        public ?array $addons,
        #[SerializedName('introduction_price_configuration')]
        public ?array $introductionPriceConfiguration,
    ) {
    }
}
