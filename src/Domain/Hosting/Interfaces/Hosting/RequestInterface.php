<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

interface RequestInterface
{
    /**
     * @return array<mixed>
     */
    public function getMessage(): array;
}
