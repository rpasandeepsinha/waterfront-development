<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData;

use Waterfront\Domain\Products\Enums\ProductGroupType;

/**
 * Read /docs/Domain/Orders/DTO/CartOrderLines/MetaData/README.md for information on how to add/modify DTOs.
 */
class ExtensionMetaData extends MetaData
{
    public function __construct(
        ProductGroupType $type,
        public ?string $transferSecret,
        public ?bool $privateWhois,
        public ?int $contactId,
    ) {
        parent::__construct($type);
    }
}
