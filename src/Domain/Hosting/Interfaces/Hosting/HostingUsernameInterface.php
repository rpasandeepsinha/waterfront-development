<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

interface HostingUsernameInterface
{
    public function generateUsername(): string;
}
