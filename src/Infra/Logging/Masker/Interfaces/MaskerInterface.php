<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\Masker\Interfaces;

interface MaskerInterface
{
    /**
     * Masks keys in the request or response body body.
     */
    public function mask(string $body, MaskKeysInterface $maskKeys): string;
}
