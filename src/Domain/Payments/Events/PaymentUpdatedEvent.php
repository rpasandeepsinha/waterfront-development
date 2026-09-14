<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Events;

use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Payments\Models\Result;

class PaymentUpdatedEvent
{
    public function __construct(
        private readonly Payment $payment,
        private readonly Result $paymentResult,
    ) {
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function getPaymentResult(): Result
    {
        return $this->paymentResult;
    }
}
