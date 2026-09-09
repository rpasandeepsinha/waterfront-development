<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Mandates;

readonly class MollieMandateDetailsDTO
{
    public function __construct(
        public string|null $consumerName,
        public string|null $consumerAccount,
        public string|null $consumerBic,
    ) {
    }
}
