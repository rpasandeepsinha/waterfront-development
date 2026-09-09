<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Mandates;

use Symfony\Component\Serializer\Attribute\Ignore;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;

interface MollieMandateCreateInterface
{
    #[Ignore]
    public function getIdentifyingValue(): string;

    public function getMethod(): MollieMandateMethod;

    public function getSignatureDate(): string;

    /**
     * Set our own made up reference, this is NOT the Mollie mandate id!
     *
     * This reference is visible in the customer's bank transactions and used to identify the mandate.
     */
    public function setMandateReference(string $mandateReference): void;
}
