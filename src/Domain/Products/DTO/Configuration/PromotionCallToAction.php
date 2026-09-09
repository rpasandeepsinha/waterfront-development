<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\DTO\Configuration;

use Symfony\Component\Serializer\Attribute\SerializedName;

readonly class PromotionCallToAction
{
    public function __construct(
        public string $title,
        #[SerializedName('button_text')]
        public ?string $buttonText,
        public string $description,
        #[SerializedName('destination_url')]
        public string $destinationUrl,
        #[SerializedName('price_description')]
        public string $priceDescription,
    ) {
    }
}
