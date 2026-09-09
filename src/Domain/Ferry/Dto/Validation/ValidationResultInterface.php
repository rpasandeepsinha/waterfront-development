<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\Validation;

interface ValidationResultInterface
{
    /**
     * @return array<mixed>
     */
    public function toArray(): array;
}
