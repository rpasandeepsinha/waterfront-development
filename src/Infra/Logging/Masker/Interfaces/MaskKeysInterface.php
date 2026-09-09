<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\Masker\Interfaces;

interface MaskKeysInterface
{
    /**
     * @return array<string>
     */
    public function getMaskKeys(): array;
}
