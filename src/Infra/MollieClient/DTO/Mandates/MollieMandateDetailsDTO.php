<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Mandates;

readonly class MollieMandateDetailsDTO
{
    public function __construct(
        public ?string $consumerName,
        public ?string $consumerAccount,
        public ?string $consumerBic,
    ) {
    }
}
