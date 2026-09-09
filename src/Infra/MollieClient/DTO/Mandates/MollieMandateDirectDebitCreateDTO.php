<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Mandates;

use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;

class MollieMandateDirectDebitCreateDTO implements MollieMandateCreateInterface
{
    public MollieMandateMethod $method;

    public function __construct(
        public readonly string $consumerName,
        public readonly string $consumerAccount,
        public readonly string $signatureDate, // Y-m-d
        public readonly string|null $consumerBic = null,
        public string|null $mandateReference = null // our own reference
    ) {
        $this->method = MollieMandateMethod::DIRECTDEBIT;
    }

    public function getIdentifyingValue(): string
    {
        return $this->consumerAccount;
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
