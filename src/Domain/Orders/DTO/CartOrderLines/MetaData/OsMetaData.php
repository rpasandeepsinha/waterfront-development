<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData;

use Waterfront\Domain\Products\Enums\ProductGroupType;

/**
 * Read /docs/Domain/Orders/DTO/CartOrderLines/MetaData/README.md for information on how to add/modify DTOs.
 */
class OsMetaData extends MetaData
{
    /**
     * @param ?non-empty-string $sshKeyUuid
     */
    public function __construct(
        ProductGroupType $type,
        public ?string $sshKeyUuid,
    ) {
        parent::__construct($type);
    }
}
