<?php

declare(strict_types=1);

namespace Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Waterfront\Domain\Products\Enums\ProductGroupType;

/**
 * Read /docs/Domain/Orders/DTO/CartOrderLines/MetaData/README.md for information on how to add/modify DTOs.
 */
#[DiscriminatorMap(typeProperty: 'type', mapping: [
    ProductGroupType::CLOUDSTACK_OS->value => OsMetaData::class,
    ProductGroupType::EXTENSION->value => ExtensionMetaData::class,
    ProductGroupType::MICROSOFT_365->value => Microsoft365MetaData::class,
])]
abstract class MetaData
{
    public function __construct(
        public ProductGroupType $type,
    ) {
    }
}
