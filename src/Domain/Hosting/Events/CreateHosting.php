<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Events;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;

class CreateHosting
{
    public function __construct(
        public readonly string $subscriptionUuid,
        public readonly ?string $technicalStatus,
        public readonly string $contactPersonName,
        public readonly string $contactEmail,
        public readonly ?string $domain,
        public readonly Customer $customer,
        public readonly Product $product,
        public readonly ?int $serverId,
    ) {
    }
}
