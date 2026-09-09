<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData;

use Waterfront\Domain\Products\Enums\ProductGroupType;

/**
 * Read /docs/Domain/Orders/DTO/CartOrderLines/MetaData/README.md for information on how to add/modify DTOs.
 */
class Microsoft365MetaData extends MetaData
{
    public function __construct(
        ProductGroupType $type,
        public string $tenantName,
        public ?string $tenantId,
    ) {
        parent::__construct($type);
    }
}
