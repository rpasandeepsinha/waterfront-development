<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Services;

use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\MetaData;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\Microsoft365MetaData;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;

class Microsoft365TenantService
{
    public function __construct(private readonly CartSerializerFactory $cartSerializerFactory)
    {
    }

    public function getMicrosoft365MetaData(string $metaData): Microsoft365MetaData
    {
        $metaData = $this->cartSerializerFactory->get()
            ->deserialize($metaData, MetaData::class, 'json');

        assert($metaData instanceof Microsoft365MetaData);

        return $metaData;
    }
}
