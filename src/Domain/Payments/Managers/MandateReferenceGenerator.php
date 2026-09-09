<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Managers;

use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Domain\Payments\Models\MollieCustomer;

class MandateReferenceGenerator
{
    public function generateMandateReference(MollieCustomer $mollieCustomer, Mandate $mandate): string
    {
        return sprintf(
            'C%dM%d',
            $mollieCustomer->customer->customer_number,
            $mandate->id,
        );
    }
}
