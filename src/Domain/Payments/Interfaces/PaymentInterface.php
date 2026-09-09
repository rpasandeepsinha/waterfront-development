<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Interfaces;

use Waterfront\Domain\Payments\Models\CreatePayment\PaymentParameters;
use Waterfront\Domain\Payments\Models\Result;

interface PaymentInterface
{
    public function createPayment(PaymentParameters $parameters): Result;

    public function fetchPayment(string $externalId): Result;
}
