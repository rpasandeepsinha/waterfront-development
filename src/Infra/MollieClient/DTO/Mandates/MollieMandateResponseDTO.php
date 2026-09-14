<?php

declare(strict_types=1);

namespace Waterfront\Infra\MollieClient\DTO\Mandates;

use DateTimeImmutable;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;
use Waterfront\Infra\MollieClient\Enums\MollieMandateStatus;

readonly class MollieMandateResponseDTO
{
    public function __construct(
        public string $resource,
        public string $id,
        public string $mode,
        public MollieMandateStatus $status,
        public MollieMandateMethod $method,
        public MollieMandateDetailsDTO $details,
        public ?string $mandateReference,
        public string $signatureDate,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
