<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Mandates;

use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;

class MollieMandatePayPalCreateDTO implements MollieMandateCreateInterface
{
    public MollieMandateMethod $method;

    public function __construct(
        public readonly string $consumerName,
        public readonly string $consumerEmail,
        public readonly string $paypalBillingAgreementId,
        public string $signatureDate, // Y-m-d
        public string|null $mandateReference = null // our own reference
    ) {
        $this->method = MollieMandateMethod::PAYPAL;
    }

    public function getIdentifyingValue(): string
    {
        return $this->consumerEmail;
    }

    public function getMethod(): MollieMandateMethod
    {
        return $this->method;
    }

    public function getSignatureDate(): string
    {
        return $this->signatureDate;
    }

    public function setMandateReference(string $mandateReference): void
    {
        $this->mandateReference = $mandateReference;
    }
}
