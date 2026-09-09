<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

interface SessionTokenInterface extends ClientInterface
{
    public function getSsoUrl(
        string $username,
        string $ipAddress,
        bool $redirectToMail = false
    ): string;

    public function getServerSsoUrl(): string;
}
