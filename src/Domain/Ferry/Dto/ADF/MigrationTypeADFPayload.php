<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ADF;

interface MigrationTypeADFPayload
{
    /**
     * @return array<mixed>
     */
    public function toArray(): array;
}
